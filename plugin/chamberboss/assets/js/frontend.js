/**
 * Chamberboss Frontend JavaScript
 */
(function($) {
    'use strict';
    
    var Chamberboss = {
        
        /**
         * Initialize frontend functionality
         */
        init: function() {
            this.initMemberRegistration();
            this.initListingSubmission();
            this.initDirectoryFilters();
            this.initImagePreviews();
        },
        
        /**
         * Initialize member registration form
         */
        initMemberRegistration: function() {
            var $form = $('#chamberboss-member-registration');
            if (!$form.length) return;
            
            $form.on('submit', this.handleMemberRegistration.bind(this));
        },
        
        /**
         * Initialize listing submission form
         */
        initListingSubmission: function() {
            var $form = $('#chamberboss-listing-submission');
            if (!$form.length) return;
            
            $form.on('submit', this.handleListingSubmission.bind(this));
        },
        
        /**
         * Initialize directory filters
         */
        initDirectoryFilters: function() {
            var $form = $('.directory-search-form');
            if (!$form.length) return;
            
            // Auto-submit on category change
            $form.find('.directory-category-filter').on('change', function() {
                $form.submit();
            });
            
            // Prevent empty searches
            $form.on('submit', function(e) {
                var searchValue = $(this).find('.directory-search-input').val().trim();
                var categoryValue = $(this).find('.directory-category-filter').val();
                
                if (!searchValue && !categoryValue) {
                    e.preventDefault();
                    return false;
                }
            });
        },
        
        /**
         * Initialize image previews
         */
        initImagePreviews: function() {
            $('input[type="file"][accept*="image"]').on('change', function(e) {
                var file = e.target.files[0];
                var $input = $(this);
                var $preview = $input.siblings('.image-preview');
                
                if (!$preview.length) {
                    $preview = $('<div class="image-preview"></div>');
                    $input.after($preview);
                }
                
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $preview.html('<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 6px; margin-top: 10px;">');
                    };
                    reader.readAsDataURL(file);
                } else {
                    $preview.empty();
                }
            });
        },
        
        /**
         * Handle member registration form submission
         */
        handleMemberRegistration: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $submitButton = $form.find('button[type="submit"]');
            var $messages = $('#registration-messages');
            
            // Disable form and show loading
            this.setFormLoading($form, true);
            $submitButton.prop('disabled', true).html('<span class="loading-spinner"></span>' + chamberboss_frontend.strings.processing);
            
            // Prepare form data
            var formData = new FormData($form[0]);
            formData.append('action', 'chamberboss_register_member');
            
            // Submit via AJAX
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="form-message success">' + response.data.message + '</div>');
                        $form[0].reset();
                        
                        // Redirect if specified
                        var redirectUrl = $form.data('redirect-url');
                        if (redirectUrl) {
                            setTimeout(function() {
                                window.location.href = redirectUrl;
                            }, 2000);
                        }
                    } else {
                        $messages.html('<div class="form-message error">' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $messages.html('<div class="form-message error">' + chamberboss_frontend.strings.error + '</div>');
                },
                complete: function() {
                    // Re-enable form
                    Chamberboss.setFormLoading($form, false);
                    $submitButton.prop('disabled', false).text($submitButton.data('original-text') || 'Register');
                }
            });
        },
        
        /**
         * Handle listing submission form
         */
        handleListingSubmission: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $submitButton = $form.find('button[type="submit"]');
            var $messages = $('#listing-messages');
            
            // Validate required fields
            var isValid = this.validateListingForm($form);
            if (!isValid) {
                return;
            }
            
            // Disable form and show loading
            this.setFormLoading($form, true);
            $submitButton.prop('disabled', true).html('<span class="loading-spinner"></span>' + chamberboss_frontend.strings.processing);
            
            // Prepare form data
            var formData = new FormData($form[0]);
            formData.append('action', 'chamberboss_submit_listing');
            
            // Submit via AJAX
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $messages.html('<div class="form-message success">' + response.data.message + '</div>');
                        $form[0].reset();
                        $('.image-preview').empty();
                        
                        // Redirect if specified
                        var redirectUrl = $form.data('redirect-url');
                        if (redirectUrl) {
                            setTimeout(function() {
                                window.location.href = redirectUrl;
                            }, 2000);
                        }
                    } else {
                        $messages.html('<div class="form-message error">' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $messages.html('<div class="form-message error">' + chamberboss_frontend.strings.error + '</div>');
                },
                complete: function() {
                    // Re-enable form
                    Chamberboss.setFormLoading($form, false);
                    $submitButton.prop('disabled', false).text($submitButton.data('original-text') || 'Submit Listing');
                }
            });
        },
        
        /**
         * Validate listing form
         */
        validateListingForm: function($form) {
            var isValid = true;
            var $messages = $('#listing-messages');
            
            // Clear previous messages
            $messages.empty();
            
            // Check required fields
            var requiredFields = [
                { field: 'listing_title', message: 'Business name is required.' },
                { field: 'listing_description', message: 'Business description is required.' }
            ];
            
            var errors = [];
            
            requiredFields.forEach(function(item) {
                var $field = $form.find('[name="' + item.field + '"]');
                var value = $field.val().trim();
                
                if (!value) {
                    errors.push(item.message);
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Validate email format if provided
            var email = $form.find('[name="listing_email"]').val().trim();
            if (email && !this.isValidEmail(email)) {
                errors.push('Please enter a valid email address.');
                $form.find('[name="listing_email"]').addClass('error');
                isValid = false;
            }
            
            // Validate website URL if provided
            var website = $form.find('[name="listing_website"]').val().trim();
            if (website && !this.isValidUrl(website)) {
                errors.push('Please enter a valid website URL.');
                $form.find('[name="listing_website"]').addClass('error');
                isValid = false;
            }
            
            // Show validation errors
            if (errors.length > 0) {
                var errorHtml = '<div class="form-message error"><ul>';
                errors.forEach(function(error) {
                    errorHtml += '<li>' + error + '</li>';
                });
                errorHtml += '</ul></div>';
                $messages.html(errorHtml);
            }
            
            return isValid;
        },
        
        /**
         * Set form loading state
         */
        setFormLoading: function($form, loading) {
            if (loading) {
                $form.addClass('form-loading');
            } else {
                $form.removeClass('form-loading');
            }
        },
        
        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        /**
         * Validate URL format
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type) {
            type = type || 'info';
            
            var $notification = $('<div class="chamberboss-notification chamberboss-notification-' + type + '">' + message + '</div>');
            
            $('body').append($notification);
            
            // Animate in
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 5000);
        },
        
        /**
         * Smooth scroll to element
         */
        scrollTo: function(target, offset) {
            offset = offset || 0;
            var $target = $(target);
            
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - offset
                }, 500);
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        Chamberboss.init();
    });
    
    // Make Chamberboss object globally available
    window.Chamberboss = Chamberboss;
    
})(jQuery);

/**
 * Directory search enhancements
 */
jQuery(document).ready(function($) {
    
    // Live search functionality
    var searchTimeout;
    $('.directory-search-input').on('input', function() {
        var $input = $(this);
        var $form = $input.closest('form');
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            if ($input.val().length >= 3 || $input.val().length === 0) {
                // Auto-submit after 500ms delay
                // $form.submit();
            }
        }, 500);
    });
    
    // Clear filters functionality
    $('.clear-filters').on('click', function(e) {
        e.preventDefault();
        
        var $form = $('.directory-search-form');
        $form.find('input[type="text"], input[type="search"]').val('');
        $form.find('select').prop('selectedIndex', 0);
        $form.submit();
    });
    
    // Listing card hover effects
    $('.listing-card').hover(
        function() {
            $(this).addClass('hovered');
        },
        function() {
            $(this).removeClass('hovered');
        }
    );
    
    // Layout toggle functionality
    $('.layout-toggle').on('click', 'button', function() {
        var $button = $(this);
        var layout = $button.data('layout');
        var $directory = $('.chamberboss-directory');
        var $listings = $('.directory-listings');
        
        // Update active button
        $button.addClass('active').siblings().removeClass('active');
        
        // Update directory layout
        $directory.attr('data-layout', layout);
        $listings.removeClass('directory-grid directory-list').addClass('directory-' + layout);
        
        // Store preference in localStorage
        localStorage.setItem('chamberboss_directory_layout', layout);
    });
    
    // Restore layout preference
    var savedLayout = localStorage.getItem('chamberboss_directory_layout');
    if (savedLayout) {
        $('.layout-toggle button[data-layout="' + savedLayout + '"]').click();
    }
    
    // Infinite scroll (optional enhancement)
    if ($('.directory-pagination').length && typeof window.IntersectionObserver !== 'undefined') {
        var loadMoreObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var $nextLink = $('.directory-pagination .next');
                    if ($nextLink.length) {
                        // Load next page via AJAX
                        loadMoreListings($nextLink.attr('href'));
                    }
                }
            });
        });
        
        var $loadTrigger = $('.directory-pagination');
        if ($loadTrigger.length) {
            loadMoreObserver.observe($loadTrigger[0]);
        }
    }
    
    /**
     * Load more listings via AJAX
     */
    function loadMoreListings(url) {
        if (!url) return;
        
        $.get(url)
            .done(function(data) {
                var $newContent = $(data).find('.directory-listings .listing-card');
                var $newPagination = $(data).find('.directory-pagination');
                
                if ($newContent.length) {
                    $('.directory-listings').append($newContent);
                    $('.directory-pagination').replaceWith($newPagination);
                    
                    // Trigger custom event
                    $(document).trigger('chamberboss:listings_loaded', [$newContent]);
                }
            })
            .fail(function() {
                console.log('Failed to load more listings');
            });
    }
});

/**
 * Form validation enhancements
 */
jQuery(document).ready(function($) {
    
    // Real-time validation
    $('form input, form textarea, form select').on('blur', function() {
        var $field = $(this);
        var value = $field.val().trim();
        var isRequired = $field.prop('required') || $field.hasClass('required');
        
        // Clear previous error state
        $field.removeClass('error');
        $field.siblings('.field-error').remove();
        
        // Validate required fields
        if (isRequired && !value) {
            $field.addClass('error');
            $field.after('<span class="field-error">This field is required.</span>');
            return;
        }
        
        // Validate email fields
        if ($field.attr('type') === 'email' && value && !Chamberboss.isValidEmail(value)) {
            $field.addClass('error');
            $field.after('<span class="field-error">Please enter a valid email address.</span>');
            return;
        }
        
        // Validate URL fields
        if ($field.attr('type') === 'url' && value && !Chamberboss.isValidUrl(value)) {
            $field.addClass('error');
            $field.after('<span class="field-error">Please enter a valid URL.</span>');
            return;
        }
        
        // Validate phone fields
        if ($field.attr('type') === 'tel' && value) {
            var phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
                $field.addClass('error');
                $field.after('<span class="field-error">Please enter a valid phone number.</span>');
                return;
            }
        }
    });
    
    // Character counter for textareas
    $('textarea[maxlength]').each(function() {
        var $textarea = $(this);
        var maxLength = parseInt($textarea.attr('maxlength'));
        var $counter = $('<div class="char-counter"><span class="current">0</span> / ' + maxLength + '</div>');
        
        $textarea.after($counter);
        
        $textarea.on('input', function() {
            var currentLength = $textarea.val().length;
            $counter.find('.current').text(currentLength);
            
            if (currentLength > maxLength * 0.9) {
                $counter.addClass('warning');
            } else {
                $counter.removeClass('warning');
            }
        });
    });
});

/**
 * Accessibility enhancements
 */
jQuery(document).ready(function($) {
    
    // Skip link functionality
    $('<a href="#main-content" class="skip-link">Skip to main content</a>')
        .prependTo('body')
        .on('click', function(e) {
            e.preventDefault();
            $('#main-content, main, .main-content').first().focus();
        });
    
    // Keyboard navigation for listing cards
    $('.listing-card').attr('tabindex', '0').on('keydown', function(e) {
        if (e.which === 13 || e.which === 32) { // Enter or Space
            e.preventDefault();
            $(this).find('a').first()[0].click();
        }
    });
    
    // ARIA labels for form fields
    $('input, textarea, select').each(function() {
        var $field = $(this);
        var $label = $('label[for="' + $field.attr('id') + '"]');
        
        if ($label.length && !$field.attr('aria-label')) {
            $field.attr('aria-label', $label.text());
        }
    });
    
    // Focus management for modals/popups
    $(document).on('keydown', function(e) {
        if (e.which === 27) { // Escape key
            $('.modal, .popup').hide();
        }
    });
});

