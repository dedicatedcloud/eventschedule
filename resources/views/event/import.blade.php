<script src="{{ asset('js/vue.global.prod.js') }}"></script>

@if (auth()->user())
<div class="flex justify-between items-center gap-6 my-6 pb-2">
    @if (is_rtl())
        <!-- RTL Layout: Cancel button on left, title on right -->
        <div class="flex items-center gap-3">
            <button onclick="history.back()" type="button" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                {{ __('messages.cancel') }}
            </button>
        </div>
        
        <div class="text-right">
            <h2 class="text-xl font-bold leading-7 text-gray-900 dark:text-gray-100x sm:truncate sm:text-2xl sm:tracking-tight">
                {{ __('messages.import_event') }}
            </h2>
        </div>
    @else
        <!-- LTR Layout: Title on left, cancel button on right -->
        <div>
            <h2 class="text-xl font-bold leading-7 text-gray-900 dark:text-gray-100x sm:truncate sm:text-2xl sm:tracking-tight">
                {{ __('messages.import_event') }}
            </h2>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="history.back()" type="button" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                {{ __('messages.cancel') }}
            </button>
        </div>
    @endif
</div>
@endif

<form method="post"
    action="{{ isset($isGuest) && $isGuest ? route('event.guest_import', ['subdomain' => $role->subdomain]) : route('event.import', ['subdomain' => $role->subdomain]) }}"
    enctype="multipart/form-data"
    id="event-import-app">

    @csrf

        <div v-if="!preview || !preview.parsed || preview.parsed.length === 0">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow-md rounded-lg">
            <div class="max-w-none">
                <!-- Main desktop grid -->
                <div class="lg:grid lg:grid-cols-2 lg:gap-6">
                    <!-- Left column: Combined Textarea and Image -->
                    <div>
                        <!-- Combined textarea and image section -->
                        <div class="lg:mb-0 mb-4">
                            <x-input-label for="event_details" :value="__('messages.event_details')" />
                            <div class="relative">
                                <textarea id="event_details" 
                                    name="event_details" 
                                    rows="6"
                                    v-model="eventDetails"
                                    v-bind:readonly="savedEvent"
                                    @input="handleInputChange"
                                    @paste="handlePaste" 
                                    @dragover.prevent="dragOverDetails"
                                    @dragleave.prevent="dragLeaveDetails"
                                    @drop.prevent="handleDetailsImageDrop"
                                    autofocus {{ config('services.google.gemini_key') ? '' : 'disabled' }}
                                    :class="['mt-1 block w-full pr-24 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-[#4E81FA] dark:focus:border-[#4E81FA] focus:ring-[#4E81FA] dark:focus:ring-[#4E81FA] rounded-md shadow-sm transition-all duration-200', 
                                        isDraggingDetails ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 ring-2 ring-blue-200 dark:ring-blue-800' : '']"
                                    placeholder="{{ __('messages.drag_drop_image_or_type_text') }}"></textarea>
                                
                                <!-- Drop message overlay for textarea -->
                                <div v-if="isDraggingDetails" 
                                     class="absolute inset-0 flex items-center justify-center bg-blue-50 dark:bg-blue-900/30 border-2 border-blue-500 rounded-md z-10">
                                    <div class="text-center">
                                        <svg class="mx-auto h-8 w-8 text-blue-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        <p class="text-blue-700 dark:text-blue-300 font-medium">Drop files here</p>
                                    </div>
                                </div>
                                
                                <!-- Image preview overlay -->
                                <div v-if="detailsImage" 
                                     class="absolute bottom-3 left-3 w-16 h-16 rounded-md overflow-hidden border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 shadow-sm">
                                    <img v-if="detailsImageUrl" 
                                         :src="detailsImageUrl" 
                                         class="object-cover w-full h-full" 
                                         alt="Event details image preview">
                                    <div v-else class="flex items-center justify-center h-full text-gray-400">
                                        <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                            <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                    
                                    <!-- Remove image button -->
                                    <button 
                                        @click="removeDetailsImage"
                                        type="button"
                                        class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-xs transition-colors"
                                        title="{{ __('messages.remove_image') }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <!-- Plus icon button for file picker -->
                                <button 
                                    v-if="!detailsImage"
                                    type="button"
                                    @click="openDetailsFileSelector"
                                    class="absolute right-16 bottom-3 p-2 rounded-md bg-blue-500 hover:bg-blue-600 text-white transition-colors"
                                    title="{{ __('messages.add_image') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                </button>
                                
                                <!-- Submit button with up arrow -->
                                <button 
                                    v-if="!isLoading"
                                    type="button"
                                    @click="handleSubmit"
                                    :disabled="!canSubmit"
                                    :class="['absolute right-5 bottom-3 p-2 rounded-md transition-all duration-200', 
                                        canSubmit 
                                            ? 'bg-blue-500 hover:bg-blue-600 text-white cursor-pointer' 
                                            : 'bg-gray-300 dark:bg-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed']"
                                    title="{{ __('messages.submit') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                    </svg>
                                </button>
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('event_details')" />

                            @if (! config('services.google.gemini_key'))
                                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    {{ __('messages.gemini_key_required') }}
                                </div>
                            @endif

                            <!-- Error message display -->
                            <div v-if="errorMessage" class="mt-4 p-3 text-sm text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30 rounded-md">
                                @{{ errorMessage }}
                            </div>

                            <div v-if="isLoading" class="mt-4 flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                                <div class="relative">
                                    <div class="w-4 h-4 rounded-full bg-blue-500/30"></div>
                                    <div class="absolute top-0 left-0 w-4 h-4 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
                                </div>
                                <div class="inline-flex items-center">
                                    <span class="animate-pulse">
                                        {{ __('messages.loading') }}
                                    </span>
                                    <span class="ml-1 inline-flex animate-[ellipsis_1.5s_steps(4,end)_infinite]">...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right column: Instructions and help -->
                    <div class="mb-4 lg:mb-0">
                        <!-- Help section removed as requested -->
                    </div>
                </div>

            </div>
        </div>

        <!-- Show All Fields and Save All buttons when events are parsed -->
        <div v-if="preview && preview.parsed && preview.parsed.length > 0" class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow-md rounded-lg mb-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                @if (auth()->user() && auth()->user()->isAdmin())
                <div class="flex items-center mb-3 sm:mb-0">
                    <input type="checkbox" 
                            id="show_all_fields" 
                            v-model="showAllFields" 
                            @change="saveShowAllFieldsPreference"
                            class="rounded border-gray-300 text-blue-500 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50">
                    <label for="show_all_fields" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                        {{ __('messages.show_all_fields') }}
                    </label>
                </div>
                @else
                <div></div>
                @endif

                <!-- Action buttons - now includes Save All -->
                <div class="flex gap-2 self-end sm:self-auto">
                    <button @click="handleSaveAll" v-if="{{ request()->has('automate') ? 'true' : 'false' }} || preview.parsed.length > 1" type="button" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                        {{ __('messages.save_all') }}
                    </button>
                </div>
            </div>
        </div>
        </div>

        <!-- Hidden file input for details image -->
        <input type="file"
                ref="detailsFileInput"
                @change="handleDetailsFileSelect"
                accept="image/*"
                class="hidden">

        <!-- Events cards - Moved outside the main div -->
        <div v-if="preview && preview.parsed && preview.parsed.length > 0" class="space-y-6">
            <div v-for="(event, idx) in preview.parsed" :key="idx" 
                    :class="['p-4 sm:p-8 bg-white dark:bg-gray-800 shadow-md sm:rounded-lg mt-4', 
                            savedEvents[idx] ? 'border-2 border-green-500 dark:border-green-600' : '']">
                
                <!-- Card header -->
                <div v-if="savedEvents[idx] || saveErrors[idx]" :class="['px-4 py-3 -m-4 sm:-m-8 mt-4 sm:mb-4 flex justify-between items-center rounded-t-lg', 
                                savedEvents[idx] ? 'bg-green-50 dark:bg-green-900/30' : 'bg-red-50 dark:bg-red-900/30']">
                    <h3 class="font-medium text-lg">
                        <span v-if="savedEvents[idx]" class="ml-2 text-sm text-green-600 dark:text-green-400">
                            <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('messages.saved') }}
                        </span>
                        <span v-else-if="saveErrors[idx]" class="ml-2 text-sm text-red-600 dark:text-red-400">
                            <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            {{ __('messages.error') }}: @{{ saveErrors[idx] }}
                        </span>
                    </h3>                            
                </div>
                
                <!-- Card body -->
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Left column: Form fields -->
                    <div class="space-y-4">
                        <!-- Show matching event if found for this specific event -->
                        <div v-if="preview.parsed[idx].event_url" class="p-3 text-sm bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 rounded-md">
                            {{ __('messages.similar_event_found') }} - 
                            <a :href="preview.parsed[idx].event_url" 
                                target="_blank" 
                                class="underline hover:text-yellow-600 dark:hover:text-yellow-300">
                                {{ __('messages.view_event') }}
                            </a>
                        </div>
                        
                        <div>
                            <x-input-label for="name_@{{ idx }}" :value="__('messages.event_name')" />
                            <x-text-input id="name_@{{ idx }}" 
                                name="name_@{{ idx }}" 
                                type="text" 
                                class="mt-1 block w-full" 
                                v-model="preview.parsed[idx].event_name"
                                v-bind:readonly="savedEvents[idx]"
                                required />
                        </div>

                        <div>
                            <x-input-label for="venue_address1_@{{ idx }}" :value="__('messages.address')" />
                            <x-text-input id="venue_address1_@{{ idx }}" 
                                name="venue_address1_@{{ idx }}" 
                                type="text" 
                                class="mt-1 block w-full" 
                                v-model="preview.parsed[idx].event_address"
                                v-bind:readonly="preview.parsed[idx].venue_id || savedEvents[idx]"
                                placeholder="{{ $role->isCurator() ? $role->city : '' }}"
                                required
                                autocomplete="off" />
                        </div>

                        <div>
                            <label for="starts_at_@{{ idx }}" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                {{ __('messages.date_and_time') }}
                            </label>
                            <input id="starts_at_@{{ idx }}" 
                                    name="starts_at_@{{ idx }}" 
                                    type="text" 
                                    :class="'mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm datepicker_' + idx"
                                    v-bind:readonly="savedEvents[idx]"
                                    v-model="preview.parsed[idx].event_date_time"
                                    required 
                                    autocomplete="off" />
                        </div>

                        <!-- Add buttons at the bottom of the left column -->
                        <div class="mt-6 flex justify-end gap-2">
                            <template v-if="savedEvents[idx]">
                                <button v-if="!savedEventData[idx]?.is_curated" @click="handleEdit(idx)" type="button" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                                    {{ __('messages.edit') }}
                                </button>
                                <button @click="handleView(idx)" type="button" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors">
                                    {{ __('messages.view') }}
                                </button>
                            </template>
                            <template v-else>
                                <button @click="handleRemoveEvent(idx)" v-if="preview.parsed.length > 1" type="button" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                                    {{ __('messages.remove') }}
                                </button>
                                <button @click="handleClear" type="button" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                                    {{ __('messages.clear') }}
                                </button>
                                <button @click="handleSave(idx)" type="button" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors">
                                    {{ __('messages.save') }}
                                </button>
                                <!--
                                <button v-if="isCurator && preview.parsed[idx].event_url && !preview.parsed[idx].is_curated" 
                                        @click="handleCurate(idx)" 
                                        type="button" 
                                        class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition-colors">
                                    {{ __('messages.curate') }}
                                </button>
                                -->
                            </template>
                        </div>


                        <!-- JSON preview with border matching textarea -->
                        <div v-if="showAllFields" class="mt-4 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm overflow-auto bg-gray-50 dark:bg-gray-900">
                            <pre class="p-4 text-xs text-gray-800 dark:text-gray-200">@{{ JSON.stringify(preview.parsed[idx], null, 2) }}</pre>
                        </div>
                        

                    </div>
                    
                    <!-- Right column: Image -->
                    <div class="flex flex-col">
                        <div class="relative h-full flex flex-col">
                            <!-- Image preview -->
                            <div v-if="preview.parsed[idx].social_image" 
                                    class="flex-grow rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800">
                                <img v-bind:src="getSocialImageUrl(preview.parsed[idx].social_image)" 
                                        class="object-contain w-full h-full" 
                                        alt="Event preview image">
                                
                                <!-- Remove image button -->
                                <button v-if="!isLoading"
                                        @click="removeImage(idx)" 
                                        type="button"
                                        v-bind:disabled="savedEvents[idx]"
                                        class="absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full hover:bg-red-600 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Drop zone -->
                            <div v-else-if="!savedEvents[idx]"
                                    @dragover.prevent="dragOver"
                                    @dragleave.prevent="dragLeave"
                                    @drop.prevent="(e) => handleDrop(e, idx)"
                                    @click="() => openFileSelector(idx)"
                                    v-bind:class="['flex-grow flex items-center justify-center rounded-lg border-2 border-dashed cursor-pointer', 
                                            isDragging ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30' : 'border-gray-300 dark:border-gray-600']">
                                <div class="text-center py-10">
                                    <!-- Show loading spinner when uploading -->
                                    <template v-if="isUploadingImage === idx">
                                        <div class="relative mx-auto w-12 h-12">
                                            <div class="w-12 h-12 rounded-full bg-blue-500/30"></div>
                                            <div class="absolute top-0 left-0 w-12 h-12 rounded-full border-4 border-blue-500 border-t-transparent animate-spin"></div>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('messages.uploading') }}...
                                        </p>
                                    </template>
                                    <!-- Default upload icon and text -->
                                    <template v-else>
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('messages.drag_drop_image') }}
                                        </p>
                                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                            {{ __('messages.or_paste_from_clipboard') }}
                                        </p>
                                    </template>
                                </div>
                            </div>

                            <!-- Hidden file input -->
                            <input type="file" 
                                    v-bind:ref="'fileInput_' + idx"
                                    @change="(e) => handleFileSelect(e, idx)"
                                    accept="image/*"
                                    class="hidden">
                        </div>
                    </div>
                </div>
            </div>
        </div>

</form>

<script {!! nonce_attr() !!}>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize flatpickr for any existing datepickers on page load
        initializeFlatpickr();
    });

    // Function to initialize flatpickr on datepicker elements
    function initializeFlatpickr() {
        // Select all elements with datepicker_X class
        document.querySelectorAll('[class*="datepicker_"]').forEach(element => {
            // Destroy existing flatpickr instance if it exists
            if (element._flatpickr) {
                element._flatpickr.destroy();
            }
            
            // Create new flatpickr instance with EXACT same configuration as edit.blade.php
            var f = flatpickr(element, {
                allowInput: true,
                enableTime: true,
                altInput: true,
                time_24hr: "{{ $role && $role->use_24_hour_time ? 'true' : 'false' }}",
                altFormat: "{{ $role && $role->use_24_hour_time ? 'M j, Y • H:i' : 'M j, Y • h:i K' }}",
                dateFormat: "Y-m-d H:i:S",
            });
            
            // Prevent keyboard input as per edit view
            if (f && f._input) {
                f._input.onkeydown = () => false;
            }
        });
    }

    const { createApp } = Vue



    createApp({
        data() {
            return {
                eventDetails: '',
                preview: null,
                isLoading: false,
                isUploadingImage: null,
                isUploadingDetailsImage: false,
                errorMessage: null,
                savedEvents: [],
                savedEventData: [],
                saveErrors: [],
                isDragging: false,
                isDraggingDetails: false,
                showAllFields: false,
                isCurator: {{ $role->isCurator() ? 'true' : 'false' }},
                detailsImage: null,
                detailsImageUrl: null,
                currentRequestId: null,
            }
        },

        computed: {
            canSubmit() {
                return this.eventDetails.trim() || this.detailsImage;
            }
        },

        created() {
            this.loadShowAllFieldsPreference()
            
            // Add clipboard paste event listener
            document.addEventListener('paste', this.handleClipboardPaste)
        },
        
        beforeUnmount() {
            // Clean up event listener when component is destroyed
            document.removeEventListener('paste', this.handleClipboardPaste)
        },

        updated() {
            this.$nextTick(() => {
                // Call the global function to initialize flatpickr
                initializeFlatpickr();
            });
        },

        methods: {
            handleInputChange() {
                // Just update the model, don't auto-submit
                // The submit button will be enabled/disabled based on canSubmit computed property
            },

            handleSubmit() {
                if (this.canSubmit) {
                    this.fetchPreview();
                }
            },

            async fetchPreview() {
                if (!this.eventDetails.trim() && !this.detailsImage) {
                    this.preview = null;
                    return;
                }

                this.isLoading = true;
                this.preview = null;
                this.errorMessage = null;
                this.savedEvents = [];
                this.savedEventData = [];
                this.saveErrors = [];
                
                // Create a unique request ID to track the latest request
                const requestId = Date.now();
                this.currentRequestId = requestId;
                
                // Don't clear preview immediately - we'll only update it if this is still the latest request
                // when the response comes back
                
                try {
                    const formData = new FormData();
                    formData.append('event_details', this.eventDetails);
                    if (this.detailsImage) {
                        formData.append('details_image', this.detailsImage);
                    }

                    const response = await fetch('{{ isset($isGuest) && $isGuest ? route("event.guest_parse", ["subdomain" => $role->subdomain]) : route("event.parse", ["subdomain" => $role->subdomain]) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: formData
                    });

                    // If this is no longer the latest request, ignore the response
                    if (this.currentRequestId !== requestId) {
                        return;
                    }

                    // Handle HTTP error responses before trying to parse JSON
                    if (!response.ok) {
                        if (response.status === 405) {
                            throw new Error('Invalid request method');
                        }
                        if (response.status === 404) {
                            throw new Error('Resource not found');
                        }
                        if (response.status === 403) {
                            throw new Error('Permission denied');
                        }
                        if (response.status === 401) {
                            throw new Error('Unauthorized');
                        }
                        if (response.status === 500) {
                            throw new Error('Server error');
                        }
                    }

                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        throw new Error('Invalid response from server');
                    }

                    if (!response.ok) {
                        // Handle validation errors
                        if (data.errors) {
                            const errorMessages = Object.values(data.errors).flat();
                            throw new Error(errorMessages.join('\n'));
                        }
                        // Handle other types of errors
                        throw new Error(data.message || data.error || 'An unexpected error occurred');
                    }

                    // Ensure preview.parsed is always an array            
                    // TODO: remove this, it shouldn't be needed
                    if (data && data.parsed && !Array.isArray(data.parsed)) {
                        data.parsed = [data.parsed];
                    } else if (data && Array.isArray(data)) {
                        data = { parsed: data };
                    }

                    // Now that we have valid data and this is still the latest request, update the preview
                    this.preview = data;
                    
                    // Initialize arrays to track saved events and errors
                    if (Array.isArray(this.preview.parsed)) {
                        this.savedEvents = new Array(this.preview.parsed.length).fill(false);
                        this.savedEventData = new Array(this.preview.parsed.length).fill(null);
                        this.saveErrors = new Array(this.preview.parsed.length).fill(false);
                    }
                    
                    // Initialize datepickers after preview is loaded
                    this.$nextTick(() => {
                        initializeFlatpickr();
                    });
                } catch (error) {
                    // Only show error if this is still the latest request
                    if (this.currentRequestId === requestId) {
                        console.error('Error fetching preview:', error)
                        this.errorMessage = error.message || 'An error occurred while fetching the preview';
                    }
                } finally {
                    // Only update loading state if this is still the latest request
                    if (this.currentRequestId === requestId) {
                        this.isLoading = false;
                    }
                }
            },
            
            handlePaste(event) {
                // Prevent the default paste behavior
                event.preventDefault()
                // Get the pasted text
                const pastedText = event.clipboardData.getData('text')
                // Update the model manually
                this.eventDetails = pastedText
                // Don't auto-submit - user must click the submit button
            },

            handleEdit(idx) {
                if (this.savedEvents[idx] && this.savedEventData[idx]) {
                    window.open(this.savedEventData[idx].edit_url, '_blank');
                }
            },

            handleView(idx) {
                if (this.savedEvents[idx] && this.savedEventData[idx]) {
                    window.open(this.savedEventData[idx].view_url, '_blank');
                }
            },

            async handleSave(idx) {
                this.errorMessage = null;
                // Reset error state for this event
                this.saveErrors[idx] = false;
                
                try {
                    // Get data from the Vue model
                    if (!this.preview?.parsed?.[idx]) {
                        throw new Error('Event data not found');
                    }
                    
                    const parsed = this.preview.parsed[idx];
                    
                    let dateValue = null; // Declare dateValue variable here                        
                    let dateElement = document.querySelector(`.datepicker_${idx}`);
                    dateValue = dateElement.value;
                    
                    // Ensure the date has seconds
                    if (dateValue && dateValue.length === 16) { // Format: "YYYY-MM-DD HH:MM"
                        dateValue += ":00"; // Add seconds
                    }
                    
                    if (!dateValue) {
                        throw new Error('Date and time are required');
                    }
                    
                    // Prepare members data
                    const members = {};
                    
                    if (parsed.performers && parsed.performers.length > 0) {
                        parsed.performers.forEach((performer, index) => {
                            members[`new_talent_${index}`] = {
                                name: performer.name,
                                name_en: performer.name_en || '',
                                email: performer.email || '',
                                website: performer.website || '',
                                language_code: '{{ $role->language_code }}',
                            };
                        });
                    } else if (parsed.talent_id) {
                        members[parsed.talent_id] = {
                            name: parsed.performer_name,
                            name_en: parsed.performer_name_en || '',
                            email: parsed.performer_email || '',
                            youtube_url: parsed.performer_youtube_url || '',
                            language_code: '{{ $role->language_code }}',
                        };
                    }
                    
                    // Get venue address from VueJS model
                    const venueAddress = parsed.event_address || "{{ $role->isCurator() ? $role->city : '' }}";

                    // Get event name from VueJS model 
                    const eventName = parsed.event_name;
                    
                    // Send request to server
                    const response = await fetch('{{ isset($isGuest) && $isGuest ? route("event.guest_import", ["subdomain" => $role->subdomain]) : route("event.import", ["subdomain" => $role->subdomain]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            venue_name: parsed.venue_name,
                            venue_name_en: parsed.venue_name_en,
                            venue_address1: venueAddress,
                            venue_address1_en: parsed.venue_address1_en,
                            venue_city: parsed.event_city,
                            venue_city_en: parsed.event_city_en,
                            venue_state: parsed.event_state,
                            venue_state_en: parsed.event_state_en,
                            venue_postal_code: parsed.event_postal_code,
                            venue_country_code: parsed.event_country_code,
                            venue_id: parsed.venue_id,
                            venue_language_code: '{{ $role->language_code }}',
                            members: members,
                            name: eventName,
                            name_en: parsed.event_name_en,
                            starts_at: dateValue,
                            duration: parsed.event_duration,
                            description: this.eventDetails ? this.eventDetails : parsed.event_details,
                            social_image: parsed.social_image,
                            registration_url: parsed.registration_url,
                            @if ($role->isCurator())
                                curators: ['{{ \App\Utils\UrlUtils::encodeId($role->id) }}'],
                            @endif
                        })
                    });
                    
                    // Handle response
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to save event');
                    }
                    
                    const data = await response.json();
                    
                    // Store the response data in savedEventData array
                    this.savedEvents[idx] = true;
                    this.savedEventData[idx] = data.event; // Store the event object with view_url and edit_url
                    
                    // Show success message
                    Toastify({
                        text: '{{ __("messages.event_created") }}',
                        duration: 3000,
                        position: 'center',
                        stopOnFocus: true,
                        style: {
                            background: '#4BB543',
                        }
                    }).showToast();
                    
                } catch (error) {
                    console.error('Error saving event:', error);
                    this.errorMessage = error.message;
                    // Set error state for this event
                    this.saveErrors[idx] = error.message || 'An error occurred while saving the event';
                }
            },

            getYouTubeEmbedUrl(url) {
                // Extract video ID from various YouTube URL formats
                const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
                const match = url.match(regExp);
                const videoId = match && match[2].length === 11 ? match[2] : null;
                
                return videoId ? `https://www.youtube.com/embed/${videoId}` : '';
            },

            getSocialImageUrl(path) {
                // Extract filename from /tmp/event_XXXXX.jpg path
                const filename = path.split('/').pop();
                return `{{ route('event.tmp_image', ['filename' => '']) }}/${filename}`;
            },

            handleClear() {
                this.eventDetails = '';
                this.detailsImage = null;
                this.detailsImageUrl = null;
                this.preview = null;
                this.savedEvents = [];
                this.savedEventData = [];
                this.errorMessage = null;
                this.$nextTick(() => {
                    document.getElementById('event_details').focus();
                });
            },

            dragOver(e) {
                this.isDragging = true
            },

            dragLeave(e) {
                this.isDragging = false
            },

            async handleDrop(e, idx) {
                this.isDragging = false
                const files = e.dataTransfer.files
                if (files.length > 0) {
                    await this.uploadImage(files[0], idx)
                }
            },

            openFileSelector(idx) {
                const fileInput = this.$refs[`fileInput_${idx}`];
                if (fileInput) {
                    if (Array.isArray(fileInput)) {
                        fileInput[0].click();
                    } else {
                        fileInput.click();
                    }
                }
            },

            async handleFileSelect(e, idx) {
                const files = e.target.files
                if (files.length > 0) {
                    await this.uploadImage(files[0], idx)
                }
            },

            async uploadImage(file, idx) {
                if (!file.type.startsWith('image/')) {
                    this.errorMessage = '{{ __("messages.invalid_image_type") }}'
                    return
                }

                this.isUploadingImage = idx;
                
                try {
                    // Create a FormData object to send the file
                    const formData = new FormData();
                    formData.append('image', file);
                    
                    // Upload the image to get a temporary URL
                    const response = await fetch('{{ isset($isGuest) && $isGuest ? route("event.guest_upload_image", ["subdomain" => $role->subdomain]) : route("event.upload_image", ["subdomain" => $role->subdomain]) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.filename) {
                        // Update the social_image property for the specific event
                        this.preview.parsed[idx].social_image = data.filename;
                    } else {
                        throw new Error(data.message || '{{ __("messages.error_uploading_image") }}');
                    }
                } catch (error) {
                    console.error('Error uploading image:', error);
                    this.errorMessage = error.message || '{{ __("messages.error_uploading_image") }}';
                } finally {
                    this.isUploadingImage = null;
                }
            },

            removeImage(idx) {
                if (this.preview && this.preview.parsed && this.preview.parsed[idx]) {
                    this.preview.parsed[idx].social_image = null;
                }
            },

            saveShowAllFieldsPreference() {
                localStorage.setItem('event_import_show_all_fields', this.showAllFields)
            },

            loadShowAllFieldsPreference() {
                const savedPreference = localStorage.getItem('event_import_show_all_fields')
                if (savedPreference !== null) {
                    this.showAllFields = savedPreference === 'true'
                }
            },

            handleRemoveEvent(idx) {
                if (confirm('{{ __("messages.confirm_remove_event") }}')) {
                    // Remove the event from the parsed array
                    this.preview.parsed.splice(idx, 1);
                    // Remove the corresponding entry in savedEvents array
                    this.savedEvents.splice(idx, 1);
                    this.savedEventData.splice(idx, 1);
                    
                    // If no events left, clear the preview
                    if (this.preview.parsed.length === 0) {
                        this.preview = null;
                    }
                    
                    // Re-initialize datepickers after removing an event
                    this.$nextTick(() => {
                        initializeFlatpickr();
                    });
                    
                    // Show success message
                    Toastify({
                        text: '{{ __("messages.event_removed") }}',
                        duration: 3000,
                        position: 'center',
                        stopOnFocus: true,
                        style: {
                            background: '#4BB543',
                        }
                    }).showToast();
                }
            },

            async handleCurate(idx) {
                // Reset error state for this event
                this.saveErrors[idx] = false;
                
                if (!this.preview?.parsed?.[idx]?.event_url) {
                    return;
                }

                const hash = this.preview.parsed[idx].event_id;

                try {
                    const url = @json(route('event.curate', ['subdomain' => $role->subdomain, 'hash' => '--hash--'])).replace('--hash--', hash);
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        this.savedEvents[idx] = true;
                        this.savedEventData[idx] = {
                            view_url: data.event_url || this.preview.parsed[idx].event_url,
                            is_curated: true
                        };
                        
                        Toastify({
                            text: '{{ __("messages.curate_event") }}',
                            duration: 3000,
                            position: 'center',
                            stopOnFocus: true,
                            style: {
                                background: '#4BB543',
                            }
                        }).showToast();
                    } else {
                        throw new Error(data.message || '{{ __("messages.error_curating_event") }}');
                    }
                } catch (error) {
                    console.error('Error curating event:', error);
                    this.errorMessage = error.message || '{{ __("messages.error_curating_event") }}';
                    // Set error state for this event
                    this.saveErrors[idx] = error.message || '{{ __("messages.error_curating_event") }}';
                }
            },

            async handleSaveAll() {
                // Check if there are any events to save
                if (!this.preview?.parsed || this.preview.parsed.length === 0) {
                    return;
                }
                
                // Prevent multiple clicks by disabling the button
                const saveAllButton = event.target;
                if (saveAllButton) {
                    saveAllButton.disabled = true;
                    saveAllButton.classList.add('opacity-50', 'cursor-not-allowed');
                }
                
                let successCount = 0;
                let errorCount = 0;
                let skippedCount = 0;
                
                // Loop through all events
                for (let idx = 0; idx < this.preview.parsed.length; idx++) {
                    // Skip already saved events
                    if (this.savedEvents[idx]) {
                        skippedCount++;
                        continue;
                    }
                    
                    // Skip events that have a matching one (indicated by event_url)
                    if (this.preview.parsed[idx].event_url) {
                        skippedCount++;
                        continue;
                    }
                    
                    try {
                        // If event has a curate button and is not already curated, curate it
                        if (this.isCurator && 
                            this.preview.parsed[idx].event_url && 
                            !this.preview.parsed[idx].is_curated) {
                            await this.handleCurate(idx);
                        } else {
                            // Otherwise save it normally
                            await this.handleSave(idx);
                        }
                        
                        // Check if the operation was successful
                        if (this.savedEvents[idx]) {
                            successCount++;
                        } else if (this.saveErrors[idx]) {
                            errorCount++;
                        }
                        
                        // Add a small delay between saves to prevent overwhelming the server
                        await new Promise(resolve => setTimeout(resolve, 500));
                    } catch (error) {
                        errorCount++;
                        console.error('Error processing event ' + idx + ':', error);
                    }
                }
                
                // Show appropriate message after all events are processed
                let message = '';
                if (errorCount === 0 && skippedCount === 0) {
                    message = '{{ __("messages.all_events_processed") }}';
                } else {
                    message = `{{ __("messages.events_processed_with_errors") }}`.replace('{success}', successCount).replace('{errors}', errorCount);
                    if (skippedCount > 0) {
                        message += ` ({{ __("messages.events_skipped") }}`.replace('{skipped}', skippedCount) + ')';
                    }
                }
                
                Toastify({
                    text: message,
                    duration: 3000,
                    position: 'center',
                    stopOnFocus: true,
                    style: {
                        background: errorCount > 0 && successCount === 0 ? '#FF0000' : 
                                    skippedCount > 0 && successCount === 0 ? '#FF9800' : '#4BB543',
                    }
                }).showToast();
                
                // Re-enable the button after processing is complete
                if (saveAllButton) {
                    saveAllButton.disabled = false;
                    saveAllButton.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            },

            async handleClipboardPaste(e) {
                // First check if we're pasting into the event details textarea
                if (document.activeElement === document.getElementById('event_details')) {
                    // Let the normal paste event handle this
                    return;
                }
                
                // Check if clipboard has image data
                if (e.clipboardData && e.clipboardData.items) {
                    const items = e.clipboardData.items;
                    
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            const file = items[i].getAsFile();
                            if (file) {
                                e.preventDefault(); // Prevent default paste behavior
                                
                                // Check if we have any events to paste to
                                if (this.preview?.parsed && this.preview.parsed.length > 0) {
                                    // Find the first event that doesn't have an image and isn't saved
                                    const idx = this.preview.parsed.findIndex((event, i) => 
                                        !event.social_image && !this.savedEvents[i]);
                                    
                                    if (idx !== -1) {
                                        await this.uploadImage(file, idx);
                                        
                                        break;
                                    }
                                } else {
                                    // No events yet, so paste to the details image
                                    await this.uploadDetailsImage(file);
                                                                            
                                    break;
                                }
                            }
                        }
                    }
                }
            },

            dragOverDetails(e) {
                e.preventDefault();
                this.isDraggingDetails = true;
            },

            dragLeaveDetails(e) {
                e.preventDefault();
                this.isDraggingDetails = false;
            },

            openDetailsFileSelector() {
                this.$refs.detailsFileInput.click();
            },

            async handleDetailsFileSelect(e) {
                const files = e.target.files;
                if (files.length > 0) {
                    await this.uploadDetailsImage(files[0]);
                }
                
                // Reset the file input to allow selecting the same file again
                e.target.value = '';
            },

            async handleDetailsImageDrop(e) {
                this.isDraggingDetails = false;
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    await this.uploadDetailsImage(files[0]);
                }
            },

            async uploadDetailsImage(file) {
                if (!file.type.startsWith('image/')) {
                    this.errorMessage = '{{ __("messages.invalid_image_type") }}';
                    return;
                }

                this.isUploadingDetailsImage = true;
                
                try {
                    this.detailsImage = file;
                    
                    // Use FileReader to create a data URL for preview
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.detailsImageUrl = e.target.result; // This will be a data URL
                    };
                    reader.readAsDataURL(file);
                    
                    // Clear any previous error messages
                    this.errorMessage = null;
                    
                    // Don't auto-submit - user must click the submit button
                } catch (error) {
                    console.error('Error uploading details image:', error);
                    this.errorMessage = error.message || '{{ __("messages.error_uploading_image") }}';
                    // Reset the image state on error
                    this.detailsImage = null;
                    this.detailsImageUrl = null;
                } finally {
                    this.isUploadingDetailsImage = false;
                }
            },

            removeDetailsImage() {
                // No need to revoke anything with data URLs
                this.detailsImage = null;
                this.detailsImageUrl = null;
                this.errorMessage = null; // Clear any error messages when removing the image
                
                // Reset the file input to allow selecting the same file again
                if (this.$refs.detailsFileInput) {
                    this.$refs.detailsFileInput.value = '';
                }
                
                // Don't auto-submit - user must click the submit button
            },

            getDetailsImageUrl() {
                if (!this.detailsImage) return '';
                
                try {
                    // Create a new URL object each time to avoid caching issues
                    return URL.createObjectURL(this.detailsImage);
                } catch (e) {
                    console.error('Error creating object URL:', e);
                    return '';
                }
            },
        }
    }).mount('#event-import-app')
</script>