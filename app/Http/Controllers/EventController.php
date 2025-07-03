<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Notification;
use App\Notifications\EventRequestNotification;
use App\Notifications\RequestDeclinedNotification;
use App\Notifications\RequestAcceptedNotification;
use App\Notifications\DeletedEventNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Utils\ColorUtils;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\EventRole;
use App\Models\User;
use App\Models\Ticket;
use App\Utils\UrlUtils;
use App\Utils\GeminiUtils;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Repos\EventRepo;
use App\Http\Requests\EventCreateRequest;
use App\Http\Requests\EventUpdateRequest;

class EventController extends Controller
{
    protected $eventRepo;

    public function __construct(EventRepo $eventRepo)
    {
        $this->eventRepo = $eventRepo;
    }

    public function deleteImage(Request $request, $subdomain)
    {
        $event_id = UrlUtils::decodeId($request->hash);
        $event = Event::findOrFail($event_id);

        if (! $request->user()->canEditEvent($event)) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        if ($request->image_type == 'flyer') {
            if ($event->flyer_image_url) {
                $path = $event->getAttributes()['flyer_image_url'];
                if (config('filesystems.default') == 'local') {
                    $path = 'public/' . $path;
                }
                Storage::delete($path);

                $event->flyer_image_url = null;
                $event->save();
            }    
        }

        return redirect(route('event.edit', ['subdomain' => $subdomain, 'hash' => $request->hash]))
                ->with('message', __('messages.deleted_image'));
    }

    public function delete(Request $request, $subdomain, $hash)
    {
        $user = $request->user();
        $event_id = UrlUtils::decodeId($hash);
        $event = Event::findOrFail($event_id);

        if (! $request->user()->canEditEvent($event)) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $event->delete();

        /*
        $role = $event->role;
        $venue = $event->venue;

        $roleEmails = $role->members()->pluck('email')->toArray();
        $venueEmails = $venue->members()->pluck('email')->toArray();
        $emails = array_unique(array_merge($roleEmails, $venueEmails));

        Notification::route('mail', $emails)->notify(new DeletedEventNotification($event, $user));
        */

        $data = [
            'subdomain' => $subdomain, 
            'tab' => 'schedule',
        ];

        return redirect(route('role.view_admin', $data))
                ->with('message', __('messages.event_deleted'));
    }


    public function create(Request $request, $subdomain)
    {
        if (! is_hosted_or_admin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $user = $request->user();
        $role = Role::subdomain($subdomain)->firstOrFail();
        
        $venue = $role->isVenue() ? $role : null;
        $schedule = $role->isTalent() ? $role : null;
        $curator = $role->isCurator() ? $role : null;

        if (! $role->email_verified_at) {
            return redirect()->back()->with('error', __('messages.email_not_verified'));
        }

        $event = new Event;
        $event->user_id = $user->id;
        $selectedMembers = [];
        
        if ($role->default_tickets) {
            $defaultTickets = json_decode($role->default_tickets, true);
            $event->ticket_currency_code = $defaultTickets['currency_code'] ?? 'USD';
            $event->payment_method = $defaultTickets['payment_method'] ?? 'cash';
            $event->payment_instructions = $defaultTickets['payment_instructions'] ?? null;
            $event->expire_unpaid_tickets = $defaultTickets['expire_unpaid_tickets'] ?? false;
            $event->ticket_notes = $defaultTickets['ticket_notes'] ?? null;
            $event->tickets = $defaultTickets['tickets'] ?? [new Ticket()];
        } else {
            $event->ticket_currency_code = 'USD';
            $event->payment_method = 'cash';
            $event->tickets = [new Ticket()];
        }

        if ($schedule) {
            $selectedMembers = [$schedule->toData()];
        }

        if ($request->date) {
            $defaultTime = Carbon::now($user->timezone)->setTime(20, 0, 0);
            $utcTime = $defaultTime->setTimezone('UTC');
            $event->starts_at = $request->date . $utcTime->format('H:i:s');
        }

        $roles = $user->roles()->get();
    
        $venues = $roles->filter(function($item) {
            if ($item->pivot->level == 'follower' && ! $item->acceptEventRequests()) {
                return false;
            }

            return $item->isVenue();
        })->map(function ($item) {
            return $item->toData();
        });
    
        $members = $roles->filter(function($item) {
            return $item->isTalent();
        })->map(function ($item) {
            return $item->toData();
        });

        $venues = array_values($venues->sortBy('name')->toArray());
        $members = array_values($members->sortBy('name')->toArray());
        
        $currencies = file_get_contents(base_path('storage/currencies.json'));
        $currencies = json_decode($currencies);

        return view('event/edit', [
            'role' => $role,
            'effectiveRole' => $role,
            'user' => $user,
            'roles' => $roles,
            'event' => $event,
            'subdomain' => $subdomain,
            'title' => __('messages.add_event'),
            'selectedVenue' => $venue,
            'venues' => $venues,
            'selectedMembers' => $selectedMembers,
            'members' => $members,
            'currencies' => $currencies,
            'event_categories' => get_translated_categories(),
        ]);
    }

    public function editAdmin(Request $request, $hash)
    {
        $event_id = UrlUtils::decodeId($hash);
        $event = Event::findOrFail($event_id);
        $user = $request->user();
        $subdomain = null;

        foreach ($event->roles as $each) {
            if ($user->isMember($each->subdomain)) {
                $subdomain = $each->subdomain;
                break;
            }
        }

        if (! $subdomain) {
            if ($event->venue && $user->isMember($event->venue->subdomain)) {
                $subdomain = $event->venue->subdomain;
            }
        }

        return redirect(route('event.edit', ['subdomain' => $subdomain, 'hash' => $hash]));
    }

    public function edit(Request $request, $subdomain, $hash)
    {
        if (! is_hosted_or_admin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $event_id = UrlUtils::decodeId($hash);
        $event = Event::with(['creatorRole', 'curators'])->findOrFail($event_id);
        $user = $request->user();

        if (! $user->canEditEvent($event)) {
            return redirect()->back();
        }

        if ($event->tickets->count() == 0) {
            $event->tickets = [
                [
                    new Ticket(),
                ]
            ];
        }

        // Use the creator role's subdomain to determine the correct groups/sub-schedules
        $creatorRole = $event->creatorRole;
        $effectiveSubdomain = $creatorRole ? $creatorRole->subdomain : $subdomain;
        $effectiveRole = Role::subdomain($effectiveSubdomain)->firstOrFail();
        
        $role = Role::subdomain($subdomain)->firstOrFail();
        $venue = $event->venue;
        $selectedMembers = [];
        foreach ($event->roles as $each) {
            if ($each->isTalent()) {
                $selectedMembers[] = $each->toData();
            }
        }

        if (! $role->email_verified_at) {
            return redirect()->back()->with('error', __('messages.email_not_verified'));
        }

        $title = __('messages.edit_event');
        
        $roles = $user->roles()->get();
    
        $venues = $roles->filter(function($item) {            
            if ($item->pivot->level == 'follower' && ! $item->acceptEventRequests()) {
                return false;
            }

            return $item->isVenue();
        })->map(function ($item) {
            return $item->toData();
        });
    
        $members = $roles->filter(function($item) {
            return $item->isTalent();
        })->map(function ($item) {
            return $item->toData();
        });

        $venues = array_values($venues->sortBy('name')->toArray());
        $members = array_values($members->sortBy('name')->toArray());
    
        $currencies = file_get_contents(base_path('storage/currencies.json'));
        $currencies = json_decode($currencies);

        $eventUrlData = $event->getGuestUrlData($subdomain, false);
        $matchingEvent = $this->eventRepo->getEvent($eventUrlData['subdomain'], $eventUrlData['slug'], false);        
        $isUnique = ! $matchingEvent || $matchingEvent->id == $event->id ? true : false;

        return view('event/edit', [
            'role' => $role,
            'effectiveRole' => $effectiveRole,
            'user' => $user,
            'roles' => $roles,
            'event' => $event,
            'subdomain' => $subdomain,
            'title' => $title,
            'selectedVenue' => $venue,
            'venues' => $venues,
            'selectedMembers' => $selectedMembers,
            'members' => $members,
            'currencies' => $currencies,
            'isUnique' => $isUnique,
            'event_categories' => get_translated_categories(),
        ]);
    }

    public function update(EventUpdateRequest $request, $subdomain, $hash)
    {
        if (! is_hosted_or_admin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $event_id = UrlUtils::decodeId($hash);
        $event = Event::with(['creatorRole', 'curators'])->findOrFail($event_id);

        if (! $request->user()->canEditEvent($event)) {
            return redirect()->back();
        }

        $role = Role::subdomain($subdomain)->firstOrFail();
        
        $this->eventRepo->saveEvent($role, $request, $event);

        if ($request->has('save_default_tickets')) {
            $role = Role::subdomain($subdomain)->firstOrFail();
            $defaultTickets = [
                'currency_code' => $event->ticket_currency_code,
                'payment_method' => $event->payment_method,
                'payment_instructions' => $event->payment_instructions,
                'expire_unpaid_tickets' => $event->expire_unpaid_tickets,
                'ticket_notes' => $event->ticket_notes,
                'tickets' => $event->tickets->map(function($ticket) {
                    return [
                        'type' => $ticket->type,
                        'quantity' => $ticket->quantity,
                        'price' => $ticket->price,
                        'description' => $ticket->description,
                    ];
                })->toArray()
            ];
            $role->default_tickets = json_encode($defaultTickets);
            $role->save();
        }

        if ($event->starts_at) {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $event->starts_at);
        } else {
            $date = Carbon::now();
        }

        $data = [
            'subdomain' => $subdomain, 
            'tab' => 'schedule',
            'month' => $date->month,
            'year' => $date->year,
        ];

        return redirect(route('role.view_admin', $data))
                ->with('message', __('messages.event_updated'));
    }

    public function accept(Request $request, $subdomain, $hash)
    {
        if (! auth()->user()->isMember($subdomain)) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $user = $request->user();
        $event_id = UrlUtils::decodeId($hash);
        $event = Event::with(['creatorRole', 'curators'])->findOrFail($event_id);
        $role = Role::subdomain($subdomain)->firstOrFail();

        // Handle venue acceptance
        if ($role->isVenue() && $event->venue && $event->venue->subdomain === $subdomain && $user->isMember($subdomain)) {
            $event->is_accepted = true;
            $event->save();
        }

        // Handle curator acceptance
        if ($role->isCurator() && $user->isMember($subdomain)) {
            $event->roles()->updateExistingPivot($role->id, ['is_accepted' => true]);
        }

        // Handle talent acceptance (existing logic)
        if ($role->isTalent() && $user->isMember($subdomain)) {
            $event->roles()->updateExistingPivot($role->id, ['is_accepted' => true]);
        }

        //$emails = $event->role->members()->pluck('email');
        //Notification::route('mail', $emails)->notify(new RequestAcceptedNotification($event));
        
        return redirect('/' . $subdomain . '/requests')
                    ->with('message', __('messages.request_accepted'));
    }

    public function decline(Request $request, $subdomain, $hash)
    {
        if (! auth()->user()->isMember($subdomain)) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $user = $request->user();
        $event_id = UrlUtils::decodeId($hash);
        $event = Event::with(['creatorRole', 'curators'])->findOrFail($event_id);
        $role = Role::subdomain($subdomain)->firstOrFail();

        // Handle venue decline
        if ($role->isVenue() && $event->venue && $event->venue->subdomain === $subdomain && $user->isMember($subdomain)) {
            $event->is_accepted = false;
            $event->save();
        }

        // Handle curator decline
        if ($role->isCurator() && $user->isMember($subdomain)) {
            $event->roles()->updateExistingPivot($role->id, ['is_accepted' => false]);
        }

        // Handle talent decline (existing logic)
        if ($role->isTalent() && $user->isMember($subdomain)) {
            $event->roles()->updateExistingPivot($role->id, ['is_accepted' => false]);
        }

        //$emails = $event->role->members()->pluck('email');
        //Notification::route('mail', $emails)->notify(new RequestDeclinedNotification($event));

        if ($request->redirect_to == 'schedule') {
            return redirect('/' . $subdomain . '/schedule')
                ->with('message', __('messages.request_declined'));
        } else {
            return redirect('/' . $subdomain . '/requests')
                ->with('message', __('messages.request_declined'));
        }        
    }

    public function store(EventCreateRequest $request, $subdomain)
    {
        if (! is_hosted_or_admin()) {
            return redirect()->back()->with('error', __('messages.not_authorized'));
        }

        $role = Role::subdomain($subdomain)->firstOrFail();
        $event = $this->eventRepo->saveEvent($role, $request, null);

        if ($request->has('save_default_tickets')) {
            $role = Role::subdomain($subdomain)->firstOrFail();
            $defaultTickets = [
                'currency_code' => $event->ticket_currency_code,
                'payment_method' => $event->payment_method,
                'payment_instructions' => $event->payment_instructions,
                'expire_unpaid_tickets' => $event->expire_unpaid_tickets,
                'ticket_notes' => $event->ticket_notes,
                'tickets' => $event->tickets->map(function($ticket) {
                    return [
                        'type' => $ticket->type,
                        'quantity' => $ticket->quantity,
                        'price' => $ticket->price,
                        'description' => $ticket->description,
                    ];
                })->toArray()
            ];
            $role->default_tickets = json_encode($defaultTickets);
            $role->save();
        }

        session()->forget('pending_venue');

        if ($event->starts_at) {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $event->starts_at);
        } else {
            $date = Carbon::now();
        }

        $data = [
            'subdomain' => $subdomain, 
            'tab' => 'schedule',
            'month' => $date->month,
            'year' => $date->year,
        ];

        return redirect(route('role.view_admin', $data))
                ->with('message', __('messages.event_created'));
    }

    public function curate(Request $request, $subdomain, $hash)
    {
        $event_id = UrlUtils::decodeId($hash);
        $event = Event::findOrFail($event_id);

        $role = Role::subdomain($subdomain)->firstOrFail();
        $role->events()->attach($event->id);
    
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('messages.curate_event'),
                'event_url' => $event->getGuestUrl($subdomain),
            ]);
        }

        return back()->with('message', __('messages.curate_event'));
    }

    public function uncurate(Request $request, $subdomain, $hash)
    {
        $event_id = UrlUtils::decodeId($hash);
        $event = Event::findOrFail($event_id);

        $role = Role::subdomain($subdomain)->firstOrFail();
        $role->events()->detach($event->id);

        return back()->with('message', __('messages.uncurate_event'));
    }

    public function showImport(Request $request, $subdomain)
    {
        $role = Role::subdomain($subdomain)->firstOrFail();

        return view('event.import', ['role' => $role]);
    }

    public function parse(Request $request, $subdomain)
    {
        $details = request()->input('event_details');
        $imageData = null;

        // Handle image data if provided
        if ($request->hasFile('details_image')) {
            $file = $request->file('details_image');
            
            // Validate file
            $this->validateUploadedFile($file);
            
            // Read file content securely
            $imageData = file_get_contents($file->getRealPath());
            
            // Generate secure filename
            $extension = $file->getClientOriginalExtension();
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array(strtolower($extension), $allowedExtensions)) {
                return back()->withErrors(['details_image' => 'Invalid file type. Only images are allowed.']);
            }
            
            $filename = '/tmp/event_' . strtolower(\Str::random(32)) . '.' . strtolower($extension);
            
            // Use move_uploaded_file for security
            if (!move_uploaded_file($file->getRealPath(), $filename)) {
                return back()->withErrors(['details_image' => 'Failed to upload image.']);
            }
            
            // Validate that it's actually an image
            $imageInfo = getimagesize($filename);
            if ($imageInfo === false) {
                unlink($filename); // Remove invalid file
                return back()->withErrors(['details_image' => 'Invalid image file.']);
            }
        }

        $parsed = GeminiUtils::parseEvent($details, $imageData);

        $role = Role::subdomain($subdomain)->firstOrFail();

        foreach ($parsed as $key => $item) {
            if ($imageData && empty($parsed[$key]['social_image']) && count($parsed) == 1) {
                $parsed[$key]['social_image'] = $filename;
            }

            if ($role->isVenue()) {
                $parsed[$key]['venue_id'] = UrlUtils::encodeId($role->id);
                $parsed[$key]['event_address'] = $role->address1;
            } elseif (! empty($item['venue_name']) && ! empty($item['event_address'])) {
                $venue = Role::where(function($query) use ($item) {
                            $query->where('name', $item['venue_name'])
                                ->when(! empty($item['venue_name_en']), function($q) use ($item) {
                                    $q->orWhere('name_en', $item['venue_name_en']); 
                                });
                        })
                        ->where(function($query) use ($item) {
                            $query->where('address1', $item['event_address'])
                                ->when(! empty($item['event_address_en']), function($q) use ($item) {
                                    $q->orWhere('address1_en', $item['event_address_en']);
                                });
                        })
                        ->where('type', 'venue')
                        ->orderBy('id')
                        ->first();

                if ($venue) {
                    $parsed[$key]['venue_id'] = UrlUtils::encodeId($venue->id);
                }
            }

            if ($role->isCurator()) {
                if (empty($item['event_country_code'])) {
                    $parsed[$key]['event_country_code'] = $role->country_code;
                }
            }

            if ($role->isTalent()) {
                $parsed[$key]['talent_id'] = UrlUtils::encodeId($role->id);
            } elseif (! empty($item['performer_name'])) {

                $followerRoleIds = RoleUser::where('user_id', auth()->user()->id)
                ->whereIn('level', ['owner', 'follower'])
                ->orderBy('id')->pluck('role_id')->toArray();

                $talent = Role::where('is_deleted', false)
                    ->where(function($query) use ($item) {
                        $query->where('name', $item['performer_name'])
                            ->when(! empty($item['performer_name_en']), function($q) use ($item) {
                                $q->orWhere('name_en', $item['performer_name_en']);
                            });
                    })
                    ->where('type', 'talent')
                    ->whereIn('id', $followerRoleIds)
                    ->orderBy('id')
                    ->first();

                if ($talent) {
                    $parsed[$key]['talent_id'] = UrlUtils::encodeId($talent->id);
                }
            }

            if (! empty($item['event_date_time'])) {
                try {
                    $eventDate = Carbon::parse($item['event_date_time']);
                } catch (\Exception $e) {
                    $parsed[$key]['event_date_time'] = null;
                    continue;
                }
                if ($eventDate->lt(now()->subDays(3)) || $eventDate->diffInMonths(now()) > 2) {
                    $parsed[$key]['event_date_time'] = null;
                }
            }

            // Check if the event is already imported
            $eventUrl = null;
            $event = Event::where('registration_url', $item['registration_url'])->first();
            if ($event) {
                $parsed[$key]['event_url'] = $event->getGuestUrl();
                $parsed[$key]['event_id'] = UrlUtils::encodeId($event->id);
                $parsed[$key]['is_curated'] = $role->isCurator() && $event->roles->contains($role->id);
            }

            // Check for similar events at the same time
            if (! $event && ! empty($item['event_date_time'])) {
                $timezone = auth()->user()->timezone;
                $eventDate = Carbon::parse($item['event_date_time'], $timezone)->setTimezone('UTC');
                $query = Event::where('starts_at', $eventDate);
                
                // Check for same venue address
                if (! empty($item['event_address'])) {
                    $similarByAddress = (clone $query)
                        ->whereHas('venue', function($q) use ($item) {
                            $q->where('address1', $item['event_address']);
                        })
                        ->first();

                    if ($similarByAddress) {
                        $parsed[$key]['event_url'] = $similarByAddress->getGuestUrl();
                        $parsed[$key]['event_id'] = UrlUtils::encodeId($similarByAddress->id);
                    }
                }

                // Check for same performer name
                if (! empty($item['performer_name']) && empty($parsed[$key]['event_url'])) {
                    $similarByPerformer = (clone $query)
                        ->whereHas('roles', function($q) use ($item) {
                            $q->where('type', 'talent')
                              ->where('name', 'like', '%' . $item['performer_name'] . '%')
                              ->when(!empty($item['performer_name_en']), function($q) use ($item) {
                                  $q->orWhere('name', 'like', '%' . $item['performer_name_en'] . '%');
                              });
                        })
                        ->first();

                    if ($similarByPerformer) {
                        $parsed[$key]['event_url'] = $similarByPerformer->getGuestUrl();
                        $parsed[$key]['event_id'] = UrlUtils::encodeId($similarByPerformer->id);
                    }
                }
            }
        }

        return response()->json($parsed);
    }
    
    /**
     * Validate uploaded file for security
     */
    private function validateUploadedFile($file)
    {
        // Check if file upload was successful
        if (!$file->isValid()) {
            throw new \Exception('File upload failed');
        }
        
        // Check file size (5MB limit)
        if ($file->getSize() > 5242880) {
            throw new \Exception('File too large. Maximum size is 5MB.');
        }
        
        // Check MIME type
        $allowedMimes = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp'
        ];
        
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid file type. Only images are allowed.');
        }
        
        // Additional security check - verify file signature
        $fileSignature = bin2hex(fread(fopen($file->getRealPath(), 'rb'), 10));
        $validSignatures = [
            'ffd8ff', // JPEG
            '89504e470d0a1a0a', // PNG
            '474946383761', // GIF87a
            '474946383961', // GIF89a
            '524946462a', // WEBP (starts with RIFF)
        ];
        
        $isValidSignature = false;
        foreach ($validSignatures as $signature) {
            if (str_starts_with($fileSignature, $signature)) {
                $isValidSignature = true;
                break;
            }
        }
        
        if (!$isValidSignature) {
            throw new \Exception('Invalid file format detected.');
        }
    }

    public function import(Request $request, $subdomain)
    {
        //\Log::info($request->all());
        //return redirect()->back();
        
        $role = Role::subdomain($subdomain)->firstOrFail();
                
        $event = $this->eventRepo->saveEvent($role, $request, null);

        if ($request->social_image) {
            $file = new \Illuminate\Http\UploadedFile($request->social_image, basename($request->social_image));
            $filename = strtolower('flyer_' . Str::random(32) . '.' . $file->getClientOriginalExtension());
            $path = $file->storeAs(config('filesystems.default') == 'local' ? '/public' : '/', $filename);

            $event->flyer_image_url = $filename;
            $event->save();
        }

        return response()->json([
            'success' => true,
            'event' => [
                'view_url' => $event->getGuestUrl($subdomain),
                'edit_url' => route('event.edit', ['subdomain' => $subdomain, 'hash' => UrlUtils::encodeId($event->id)]),
            ]
        ]);
    }

    public function uploadImage(Request $request, $subdomain)
    {
        $file = $request->file('image');
        $filename = '/tmp/event_' . strtolower(Str::random(32)) . '.' . $file->getClientOriginalExtension();
        move_uploaded_file($file->getPathname(), $filename);

        return response()->json(['success' => true, 'filename' => $filename]);
    }
}
