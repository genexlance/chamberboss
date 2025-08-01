/**
 * ChamberBoss Frontend JavaScript
 * Version: 1.0.12
 */

(function($) {
    'use strict';

    var Chamberboss = {
        stripe: null,
        elements: null,
        paymentElement: null,
        paymentIntentId: null,
        
        /**
         * Initialize frontend functionality
         */
        init: function() {
            this.initMemberRegistration();
            this.initListingSubmission();
            this.initDirectoryFilters();
            this.initImagePreviews();
            this.initMemberDashboard();
        },
        
        /**
         * Initialize member registration form
         */
        initMemberRegistration: function() {
            var $form = $('#chamberboss-member-registration');
            
            if ($form.length === 0) {
                return;
            }

            // Check if Stripe payment is needed
            var $paymentSection = $('#payment-element');
            if ($paymentSection.length > 0 && chamberboss_frontend.stripe_publishable_key) {
                this.initStripe();
            }

            // Attach form submit handler
            $form.on('submit', this.handleMemberRegistration.bind(this));
        },

        /**
         * Initialize Stripe
         */
        initStripe: function() {
            if (!window.Stripe) {
                console.error('Chamberboss: Stripe.js not loaded');
                return false;
            }

            if (!chamberboss_frontend.stripe_publishable_key) {
                console.error('Chamberboss: No Stripe publishable key found');
                return false;
            }

            try {
                // Initialize Stripe
                this.stripe = Stripe(chamberboss_frontend.stripe_publishable_key);

                // Create Payment Intent and Elements
                this.initializePaymentIntent();

                return true;
            } catch (error) {
                console.error('Chamberboss: Stripe initialization failed:', error);
                return false;
            }
        },

        /**
         * Initialize Payment Intent and Elements for form setup
         */
        initializePaymentIntent: function() {
            // Create a generic payment intent for element setup - will be replaced on form submission
            var ajaxData = {
                action: 'chamberboss_create_payment_intent',
                nonce: chamberboss_frontend.nonce,
                setup_only: true // Flag to indicate this is just for UI setup
            };
            
            $.post(chamberboss_frontend.ajax_url, ajaxData)
            .done(function(response) {
                if (response && response.success && response.data && response.data.clientSecret) {
                    console.log('Chamberboss: Payment elements initialized for UI');
                    this.initializeElements(response.data.clientSecret);
                } else {
                    console.log('Chamberboss: Payment setup failed, will show free registration');
                }
            }.bind(this))
            .fail(function(xhr, status, error) {
                console.error('Chamberboss: Payment setup failed:', error);
            });
        },

        /**
         * Initialize Stripe Elements
         */
        initializeElements: function(clientSecret) {
            try {
                this.elements = this.stripe.elements({
                    clientSecret: clientSecret
                });

                this.paymentElement = this.elements.create('payment');

                var paymentElementDiv = document.getElementById('payment-element');
                if (paymentElementDiv) {
                    this.paymentElement.mount('#payment-element');
                }
            } catch (error) {
                console.error('Chamberboss: Elements initialization failed:', error);
            }
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
            var self = this;
            
            // Validate required fields first
            var memberName = $form.find('[name="member_name"]').val().trim();
            var memberEmail = $form.find('[name="member_email"]').val().trim();
            
            if (!memberName || !memberEmail) {
                $messages.html('<div class="form-message error">Please fill in all required fields.</div>');
                return;
            }
            
            // Check if payment is required based on presence of payment element div
            var $paymentElement = $form.find('#payment-element');
            var hasPaymentElement = $paymentElement.length > 0;
            
            console.log('Chamberboss: Payment check - hasPaymentElement:', hasPaymentElement, 'stripe:', !!this.stripe, 'paymentElement:', !!this.paymentElement);
            
            if (hasPaymentElement) {
                // Payment is required - check if Stripe is properly initialized
                if (!this.stripe) {
                    $messages.html('<div class="form-message error">Payment system not initialized. Please refresh the page and try again.</div>');
                    return;
                }
                
                // If payment elements aren't ready, create them now
                if (!this.paymentElement) {
                    console.log('Chamberboss: Payment elements not ready, creating them now...');
                    this.processPaymentAndRegistration($form, $submitButton, $messages);
                } else {
                    // Elements are ready, proceed with payment
                    this.processPaymentAndRegistration($form, $submitButton, $messages);
                }
            } else {
                // No payment required, proceed with free registration
                console.log('Chamberboss: No payment required, proceeding with free registration');
                this.submitRegistration($form, $submitButton, $messages);
            }
        },
        
        /**
         * Process payment and registration
         */
        processPaymentAndRegistration: function($form, $submitButton, $messages) {
            var self = this;
            
            if (!this.stripe) {
                $messages.html('<div class="form-message error">Payment system not properly initialized</div>');
                self.resetForm($form, $submitButton);
                return;
            }
            
            $messages.html('<div class="form-message info">Creating payment...</div>');
            $submitButton.prop('disabled', true).html('<span class="loading-spinner"></span>Processing...');
            
            // Create payment intent with member data from form
            this.createPaymentIntentWithMemberData($form, function(clientSecret, paymentIntentId) {
                if (!clientSecret) {
                    $messages.html('<div class="form-message error">Failed to initialize payment</div>');
                    self.resetForm($form, $submitButton);
                    return;
                }
                
                // Initialize elements with the new client secret
                self.elements = self.stripe.elements({
                    clientSecret: clientSecret
                });
                
                self.paymentElement = self.elements.create('payment');
                
                var paymentElementDiv = document.getElementById('payment-element');
                if (paymentElementDiv) {
                    // Clear and mount the payment element
                    paymentElementDiv.innerHTML = '';
                    self.paymentElement.mount('#payment-element');
                    
                    // Show payment section if hidden
                    $('#payment-section').show();
                    
                    $messages.html('<div class="form-message info">Please complete your payment details below, then click "Complete Payment"</div>');
                    
                    // Change submit button to complete payment
                    $submitButton.prop('disabled', false).html('Complete Payment').off('click').on('click', function(e) {
                        e.preventDefault();
                        self.confirmPaymentAndSubmit($form, $submitButton, $messages, paymentIntentId);
                    });
                } else {
                    $messages.html('<div class="form-message error">Payment form not found</div>');
                    self.resetForm($form, $submitButton);
                }
            });
        },
        
        /**
         * Confirm payment after user completes details
         */
        confirmPayment: function($form, $submitButton, $messages, clientSecret) {
            var self = this;
            
            self.setFormLoading($form, true);
            $submitButton.prop('disabled', true).html('<span class="loading-spinner"></span>Processing...');
            
            // Confirm payment
            self.stripe.confirmPayment({
                elements: self.elements,
                confirmParams: {
                    return_url: window.location.href,
                },
                redirect: 'if_required'
            }).then(function(result) {
                if (result.error) {
                    $messages.html('<div class="form-message error">' + result.error.message + '</div>');
                    self.resetForm($form, $submitButton);
                } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    // Payment successful, now submit registration
                    self.submitRegistration($form, $submitButton, $messages, result.paymentIntent.id);
                } else {
                    $messages.html('<div class="form-message error">Payment processing failed</div>');
                    self.resetForm($form, $submitButton);
                }
            }).catch(function(error) {
                console.error('Chamberboss: Payment confirmation error:', error);
                $messages.html('<div class="form-message error">Payment processing failed</div>');
                self.resetForm($form, $submitButton);
            });
        },
        
        /**
         * Create payment intent with member data for registration
         */
        createPaymentIntentWithMemberData: function($form, callback) {
            var formData = new FormData($form[0]);
            formData.append('action', 'chamberboss_create_payment_intent');
            formData.append('nonce', chamberboss_frontend.nonce); // Use frontend nonce, not registration nonce
            
            // Add member data to the request
            formData.append('member_name', $form.find('[name="member_name"]').val());
            formData.append('member_email', $form.find('[name="member_email"]').val());
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        callback(response.data.clientSecret, response.data.paymentIntentId);
                    } else {
                        console.error('Payment intent creation failed:', response);
                        callback(null, null);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Payment intent creation error:', xhr.responseText);
                    callback(null, null);
                }
            });
        },
        
        /**
         * Confirm payment and submit registration
         */
        confirmPaymentAndSubmit: function($form, $submitButton, $messages, paymentIntentId) {
            var self = this;
            
            $messages.html('<div class="form-message info">Processing payment...</div>');
            $submitButton.prop('disabled', true).html('<span class="loading-spinner"></span>Processing...');
            
            // Confirm payment with Stripe
            this.stripe.confirmPayment({
                elements: this.elements,
                redirect: 'if_required'
            }).then(function(result) {
                if (result.error) {
                    $messages.html('<div class="form-message error">' + result.error.message + '</div>');
                    self.resetForm($form, $submitButton);
                } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    // Payment successful, now submit registration
                    self.submitRegistration($form, $submitButton, $messages, result.paymentIntent.id);
                } else {
                    $messages.html('<div class="form-message error">Payment confirmation failed</div>');
                    self.resetForm($form, $submitButton);
                }
            }).catch(function(error) {
                console.error('Chamberboss: Payment confirmation error:', error);
                $messages.html('<div class="form-message error">Payment processing failed</div>');
                self.resetForm($form, $submitButton);
            });
        },
        
        /**
         * Submit registration after payment
         */
        submitRegistration: function($form, $submitButton, $messages, paymentIntentId) {
            var formData = new FormData($form[0]);
            formData.append('action', 'chamberboss_register_member');
            
            if (paymentIntentId) {
                formData.append('payment_intent_id', paymentIntentId);
            }
            
            var self = this;
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    // console.log('🔧 CHAMBERBOSS: AJAX request starting'); // Removed verbose logging
                },
                success: function(response) {
                    // console.log('🔧 CHAMBERBOSS: AJAX SUCCESS callback triggered'); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: Raw response:', response); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: Response type:', typeof response); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: Response.success:', response.success); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: Response.data:', response.data); // Removed verbose logging
                    
                    // Try to parse response if it's a string
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.error('🔧 CHAMBERBOSS: Failed to parse JSON:', e);
                            console.log('🔧 CHAMBERBOSS: Raw string content:', response);
                        }
                    }
                    
                    if (response && response.success) {
                        var successHtml = '<div class="form-message success">' + response.data.message + '</div>';
                        
                        // Add debug info if available
                        if (response.data.debug) {
                            successHtml += '<div class="debug-info" style="margin-top: 10px; padding: 10px; background: #f0f0f0; font-size: 12px;">';
                            successHtml += '<strong>Debug Info:</strong><br>';
                            successHtml += 'User ID: ' + response.data.debug.user_created + '<br>';
                            successHtml += 'Member ID: ' + response.data.debug.member_created + '<br>';
                            successHtml += 'Username: ' + response.data.debug.username + '<br>';
                            successHtml += 'Timestamp: ' + response.data.debug.timestamp;
                            successHtml += '</div>';
                        }
                        
                        $messages.html(successHtml);
                        $form[0].reset();
                        
                        // Redirect to member dashboard
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 3000); // Give more time to see debug info
                        }
                    } else {
                        console.log('🔧 CHAMBERBOSS: Processing error response');
                        console.log('🔧 CHAMBERBOSS: Response success value:', response ? response.success : 'no response object');
                        var errorMessage = 'Registration failed';
                        
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response && response.message) {
                            errorMessage = response.message;
                        } else if (typeof response === 'string' && response.trim() !== '') {
                            errorMessage = 'Server response: ' + response;
                        }
                        
                        var errorHtml = '<div class="form-message error">' + errorMessage + '</div>';
                        
                        // Add debug info if available
                        if (response && response.data && response.data.debug) {
                            errorHtml += '<div class="debug-info" style="margin-top: 10px; padding: 10px; background: #ffe6e6; font-size: 12px;">';
                            errorHtml += '<strong>Debug Info:</strong><br>';
                            errorHtml += JSON.stringify(response.data.debug, null, 2);
                            errorHtml += '</div>';
                        }
                        
                        $messages.html(errorHtml);
                        self.resetForm($form, $submitButton);
                    }
                },
                error: function(xhr, status, error) {
                    // console.log('🔧 CHAMBERBOSS: AJAX ERROR callback triggered'); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: XHR object:', xhr); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: Status:', status); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: Error:', error); // Removed verbose logging
                    // console.log('🔧 CHAMBERBOSS: Response text:', xhr.responseText); // Removed verbose logging
                    
                    var errorMessage = chamberboss_frontend.strings.error;
                    if (xhr.responseText && xhr.responseText.trim() !== '') {
                        errorMessage += ' Response: ' + xhr.responseText;
                    }
                    
                    $messages.html('<div class="form-message error">' + errorMessage + '</div>');
                    self.resetForm($form, $submitButton);
                }
            });
        },
        
        /**
         * Reset form to normal state
         */
        resetForm: function($form, $submitButton) {
            this.setFormLoading($form, false);
            $submitButton.prop('disabled', false).html($submitButton.data('original-text') || 'Join & Pay Now');
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
        },
        
        /**
         * Initialize member dashboard functionality
         */
        initMemberDashboard: function() {
            this.initAddListingForm();
            this.initEditListingForm();
            this.initDeleteListing();
            this.initPasswordChange();
        },
        
        /**
         * Initialize add listing form
         */
        initAddListingForm: function() {
            var self = this;
            
            // Show/hide add listing form
            $(document).on('click', '#add-new-listing-btn', function(e) {
                e.preventDefault();
                $('#add-listing-form').slideToggle();
            });
            
            // Cancel form
            $(document).on('click', '.cancel-form', function(e) {
                e.preventDefault();
                $(this).closest('.listing-form-container').slideUp();
                $(this).closest('form')[0].reset();
            });
            
            // Handle form submission
            $(document).on('submit', '#create-listing-form', function(e) {
                e.preventDefault();
                self.handleListingSubmission(this, 'chamberboss_create_listing');
            });
        },
        
        /**
         * Initialize edit listing form
         */
        initEditListingForm: function() {
            var self = this;
            
            // Handle edit button clicks
            $(document).on('click', '.edit-listing-btn', function(e) {
                e.preventDefault();
                var listingId = $(this).data('listing-id');
                self.loadEditListingForm(listingId);
            });
            
            // Handle edit form submission
            $(document).on('submit', '#edit-listing-form form', function(e) {
                e.preventDefault();
                self.handleListingSubmission(this, 'chamberboss_update_listing');
            });
        },
        
        /**
         * Load edit listing form
         */
        loadEditListingForm: function(listingId) {
            var self = this;
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'chamberboss_get_listing_data',
                    listing_id: listingId,
                    nonce: chamberboss_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#edit-listing-content').html(response.data.form_html);
                        $('#edit-listing-form').slideDown();
                        self.scrollTo('#edit-listing-form');
                    } else {
                        self.showMessage($('#edit-listing-form'), response.data.message || 'Failed to load listing', 'error');
                    }
                },
                error: function() {
                    self.showMessage($('#edit-listing-form'), 'Connection error. Please try again.', 'error');
                }
            });
        },
        
        /**
         * Handle listing form submissions (create/update)
         */
        handleListingSubmission: function(form, action) {
            var self = this;
            var $form = $(form);
            var $submitBtn = $form.find('input[type="submit"]');
            var originalText = $submitBtn.val();
            var formData = new FormData(form);
            
            // Add action to form data
            formData.append('action', action);
            
            // Disable submit button
            $submitBtn.prop('disabled', true).val('Processing...');
            
            // Clear previous messages
            $form.find('.form-messages').empty();
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showMessage($form.find('.form-messages'), response.data.message, 'success');
                        
                        // Close the form and refresh the listings after a delay
                        setTimeout(function() {
                            // Hide any open forms
                            $('.listing-form-container').slideUp();
                            
                            // Reset the form if it's a create form
                            if ($form.attr('id') === 'create-listing-form') {
                                $form[0].reset();
                            }
                            
                            // Show global success message
                            self.showGlobalMessage(response.data.message, 'success');
                            
                            // Refresh the page to show updated listings
                            window.location.reload();
                        }, 2000);
                    } else {
                        self.showMessage($form.find('.form-messages'), response.data.message || 'An error occurred', 'error');
                    }
                },
                error: function() {
                    self.showMessage($form.find('.form-messages'), 'Connection error. Please try again.', 'error');
                },
                complete: function() {
                    // Re-enable submit button
                    $submitBtn.prop('disabled', false).val(originalText);
                }
            });
        },
        
        /**
         * Initialize delete listing functionality
         */
        initDeleteListing: function() {
            var self = this;
            
            $(document).on('click', '.delete-listing-btn', function(e) {
                e.preventDefault();
                var listingId = $(this).data('listing-id');
                var businessName = $(this).closest('tr').find('td:first').text();
                
                if (confirm('Are you sure you want to delete "' + businessName + '"? This action cannot be undone.')) {
                    self.deleteListing(listingId);
                }
            });
        },
        
        /**
         * Delete listing
         */
        deleteListing: function(listingId) {
            var self = this;
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'chamberboss_delete_listing',
                    listing_id: listingId,
                    nonce: chamberboss_frontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message and reload
                        self.showGlobalMessage(response.data.message, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        self.showGlobalMessage(response.data.message || 'Failed to delete listing', 'error');
                    }
                },
                error: function() {
                    self.showGlobalMessage('Connection error. Please try again.', 'error');
                }
            });
        },
        
        /**
         * Initialize password change functionality
         */
        initPasswordChange: function() {
            var self = this;
            
            $(document).on('submit', '#change-password-form', function(e) {
                e.preventDefault();
                self.handlePasswordChange(this);
            });
            
            // Real-time password confirmation validation
            $(document).on('input', '#confirm_password', function() {
                var newPassword = $('#new_password').val();
                var confirmPassword = $(this).val();
                var $field = $(this);
                
                if (confirmPassword && newPassword !== confirmPassword) {
                    $field.css('border-color', '#dc3545');
                } else {
                    $field.css('border-color', '');
                }
            });
        },
        
        /**
         * Handle password change
         */
        handlePasswordChange: function(form) {
            var self = this;
            var $form = $(form);
            var $submitBtn = $form.find('input[type="submit"]');
            var originalText = $submitBtn.val();
            
            // Validate passwords match
            var newPassword = $form.find('#new_password').val();
            var confirmPassword = $form.find('#confirm_password').val();
            
            if (newPassword !== confirmPassword) {
                self.showMessage($form.find('.form-messages'), 'New password and confirmation do not match.', 'error');
                return;
            }
            
            // Disable submit button
            $submitBtn.prop('disabled', true).val('Changing...');
            
            // Clear previous messages
            $form.find('.form-messages').empty();
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'chamberboss_change_password',
                    current_password: $form.find('#current_password').val(),
                    new_password: newPassword,
                    confirm_password: confirmPassword,
                    nonce: $form.find('#password_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.showMessage($form.find('.form-messages'), response.data.message, 'success');
                        form.reset(); // Clear the form
                    } else {
                        self.showMessage($form.find('.form-messages'), response.data.message || 'Failed to change password', 'error');
                    }
                },
                error: function() {
                    self.showMessage($form.find('.form-messages'), 'Connection error. Please try again.', 'error');
                },
                complete: function() {
                    // Re-enable submit button
                    $submitBtn.prop('disabled', false).val(originalText);
                }
            });
        },
        
        /**
         * Show message in a container
         */
        showMessage: function($container, message, type) {
            var cssClass = type === 'success' ? 'chamberboss-notice-success' : 'chamberboss-notice-error';
            var html = '<div class="chamberboss-notice ' + cssClass + '"><p>' + message + '</p></div>';
            $container.html(html);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $container.find('.chamberboss-notice').fadeOut();
                }, 3000);
            }
        },
        
        /**
         * Show global message (for page-level notifications)
         */
        showGlobalMessage: function(message, type) {
            var cssClass = type === 'success' ? 'chamberboss-notice-success' : 'chamberboss-notice-error';
            var html = '<div class="chamberboss-notice ' + cssClass + '" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;"><p>' + message + '</p></div>';
            
            $('body').append(html);
            
            // Auto-hide after 3 seconds
            setTimeout(function() {
                $('.chamberboss-notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
                // Initialize when document is ready
            $(document).ready(function() {
                // console.log('🔥🔥🔥 CHAMBERBOSS v1.0.7: DOCUMENT READY HANDLER CALLED! 🔥🔥🔥'); // Removed verbose logging
        // console.log('🔥 CHAMBERBOSS: Document ready state:', document.readyState); // Removed verbose logging
        // console.log('🔥 CHAMBERBOSS: Frontend data available:', typeof chamberboss_frontend !== 'undefined' ? 'YES' : 'NO'); // Removed verbose logging
        // console.log('🔥 CHAMBERBOSS: jQuery available:', !!window.jQuery); // Removed verbose logging
        
        if (typeof chamberboss_frontend !== 'undefined') {
            // console.log('🔥 CHAMBERBOSS: AJAX URL:', chamberboss_frontend.ajax_url); // Removed verbose logging
            // console.log('🔥 CHAMBERBOSS: Stripe key available:', !!chamberboss_frontend.stripe_publishable_key); // Removed verbose logging
        }
        
                        // console.log('🔥🔥🔥 CHAMBERBOSS v1.0.7: CALLING MAIN INIT FUNCTION... 🔥🔥🔥'); // Removed verbose logging
                Chamberboss.init();
                // console.log('🔥🔥🔥 CHAMBERBOSS v1.0.7: DOCUMENT READY COMPLETE! 🔥🔥🔥'); // Removed verbose logging
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

