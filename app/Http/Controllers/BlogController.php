<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Utils\GeminiUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = BlogPost::published()->orderBy('published_at', 'desc');

        // Filter by tag
        if ($request->has('tag')) {
            $query->byTag($request->tag);
        }

        // Filter by month
        if ($request->has('month') && $request->has('year')) {
            $query->byMonth($request->year, $request->month);
        }

        $posts = $query->paginate(10);

        // Get all tags for sidebar
        $allTags = BlogPost::published()
            ->whereNotNull('tags')
            ->get()
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        // Get monthly archives
        $archives = BlogPost::published()
            ->get()
            ->groupBy(function($post) {
                return $post->published_at->format('Y-m');
            })
            ->map(function($posts, $yearMonth) {
                $parts = explode('-', $yearMonth);
                return (object) [
                    'year' => $parts[0],
                    'month' => $parts[1],
                    'count' => $posts->count(),
                    'month_name' => Carbon::createFromDate($parts[0], $parts[1], 1)->format('F')
                ];
            })
            ->sortByDesc('year')
            ->sortByDesc('month')
            ->values();

        return view('blog.index', compact('posts', 'allTags', 'archives'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        return view('blog.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'tags' => 'nullable|string',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:255',
            'featured_image' => 'nullable|string|in:' . implode(',', array_keys(BlogPost::getAvailableHeaderImages())),
            'author_name' => 'nullable|string|max:255',
            'is_published' => 'boolean',
        ]);

        $data = $request->all();
        
        // Handle tags
        if ($request->has('tags')) {
            $tags = array_map('trim', explode(',', $request->tags));
            $tags = array_filter($tags);
            $data['tags'] = $tags;
        }

        // Set published_at if not provided but is_published is true
        if ($request->is_published && !$request->published_at) {
            $data['published_at'] = now()->addSeconds(rand(-60 * 60 * 60, 60 * 60 * 60));
        }

        BlogPost::create($data);

        return redirect()->route('blog.admin.index')->with('message', 'Blog post created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($slug)
    {
        $isPreview = request()->query('preview') == 1;
        if ($isPreview && auth()->check() && auth()->user()->isAdmin()) {
            $post = \App\Models\BlogPost::where('slug', $slug)->firstOrFail();
        } else {
            $post = \App\Models\BlogPost::published()->where('slug', $slug)->firstOrFail();
        }
        // Increment view count only for public views
        if (!$isPreview) {
            $post->incrementViewCount();
        }
        // Get related posts
        $relatedPosts = \App\Models\BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where(function ($query) use ($post) {
                if ($post->tags) {
                    foreach ($post->tags as $tag) {
                        $query->orWhereJsonContains('tags', $tag);
                    }
                }
            })
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->get();
        return view('blog.show', compact('post', 'relatedPosts'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BlogPost $blogPost)
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        return view('blog.edit', compact('blogPost'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BlogPost $blogPost)
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'tags' => 'nullable|string',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:255',
            'featured_image' => 'nullable|string|in:' . implode(',', array_keys(BlogPost::getAvailableHeaderImages())),
            'author_name' => 'nullable|string|max:255',
            'is_published' => 'boolean',
        ]);

        $data = $request->all();

        // Handle tags
        if ($request->has('tags')) {
            $tags = array_map('trim', explode(',', $request->tags));
            $tags = array_filter($tags);
            $data['tags'] = $tags;
        }

        // Set published_at if not provided but is_published is true
        if ($request->is_published && !$request->published_at && !$blogPost->published_at) {
            $data['published_at'] = now();
        }

        $blogPost->update($data);

        return redirect()->route('blog.admin.index')->with('message', 'Blog post updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BlogPost $blogPost)
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $blogPost->delete();

        return redirect()->route('blog.admin.index')->with('message', 'Blog post deleted successfully.');
    }

    /**
     * Admin index - show all posts (published and unpublished)
     */
    public function adminIndex()
    {
        if (!auth()->user()->isAdmin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $posts = BlogPost::orderBy('created_at', 'desc')->paginate(15);

        return view('blog.admin.index', compact('posts'));
    }

    /**
     * Generate blog post content using AI
     */
    public function generateContent(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Not authorized'], 403);
        }

        $request->validate([
            'topic' => 'required|string|max:255',
        ]);

        try {
            $topic = $request->input('topic');

            $generatedContent = GeminiUtils::generateBlogPost($topic);

            return response()->json($generatedContent);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate content: ' . $e->getMessage()], 500);
        }
    }
}
