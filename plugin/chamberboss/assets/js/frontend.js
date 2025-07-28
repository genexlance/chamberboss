/**
 * ChamberBoss Frontend JavaScript
 * Version: 1.0.7 - AJAX CONFLICT FIX
 */

// MEGA DEBUGGING: FORCE CACHE REFRESH
console.log('🚨🚨🚨 CHAMBERBOSS FRONTEND v1.0.7: JavaScript file is loading! 🚨🚨🚨');
console.log('🚨🚨🚨 CACHE BUSTER: ' + new Date().getTime() + ' 🚨🚨🚨');
alert('🔥🔥🔥 CHAMBERBOSS v1.0.7 LOADED: ' + new Date().getTime() + ' 🔥🔥🔥');
document.title = 'CHAMBERBOSS v1.0.7 LOADED - ' + document.title;

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
            console.log('🔥 CHAMBERBOSS: MAIN INIT FUNCTION CALLED!');
            console.log('🔥 CHAMBERBOSS: jQuery available:', !!window.jQuery);
            console.log('🔥 CHAMBERBOSS: $ available (inside closure):', !!$);
            console.log('🔥 CHAMBERBOSS: $ same as jQuery:', $ === jQuery);
            console.log('🔥 CHAMBERBOSS: chamberboss_frontend available:', !!window.chamberboss_frontend);
            
            this.initMemberRegistration();
            this.initListingSubmission();
            this.initDirectoryFilters();
            this.initImagePreviews();
            
            console.log('🔥 CHAMBERBOSS: MAIN INIT COMPLETE!');
        },
        
        /**
         * Initialize member registration form
         */
        initMemberRegistration: function() {
            console.log('🔥 CHAMBERBOSS: initMemberRegistration() CALLED!');
            console.log('🔥 CHAMBERBOSS: DOM ready state:', document.readyState);
            console.log('🔥 CHAMBERBOSS: Forms on page:', document.querySelectorAll('form').length);
            
            var $form = $('#chamberboss-member-registration');
            console.log('🔧 CHAMBERBOSS: Looking for registration form, found:', $form.length);
            console.log('🔧 CHAMBERBOSS: All elements with chamberboss ID:', $('[id*="chamberboss"]').length);
            
            if (!$form.length) {
                console.log('🔧 CHAMBERBOSS: No registration form found');
                return;
            }
            
            console.log('🔧 CHAMBERBOSS: Registration form found, setting up handlers');
            
            // Check for payment section
            var $paymentSection = $form.find('#payment-element');
            console.log('🔧 CHAMBERBOSS: Payment element search result:', $paymentSection.length);
            console.log('🔧 CHAMBERBOSS: Payment element HTML:', $paymentSection.length > 0 ? $paymentSection[0].outerHTML : 'NOT FOUND');
            
            // Check Stripe key availability
            console.log('🔧 CHAMBERBOSS: chamberboss_frontend object:', typeof chamberboss_frontend);
            console.log('🔧 CHAMBERBOSS: Stripe publishable key exists:', !!chamberboss_frontend.stripe_publishable_key);
            console.log('🔧 CHAMBERBOSS: Stripe publishable key value:', chamberboss_frontend.stripe_publishable_key);
            
            // Initialize Stripe if payment section exists
            if ($paymentSection.length && chamberboss_frontend.stripe_publishable_key) {
                console.log('🔧 CHAMBERBOSS: Conditions met - initializing Stripe');
                this.initStripe();
            } else {
                console.log('🔧 CHAMBERBOSS: Stripe initialization skipped');
                console.log('🔧 CHAMBERBOSS: - Payment section exists:', $paymentSection.length > 0);
                console.log('🔧 CHAMBERBOSS: - Stripe key available:', !!chamberboss_frontend.stripe_publishable_key);
            }
            
            console.log('🔥 CHAMBERBOSS: Attempting to attach form submit handler...');
            console.log('🔥 CHAMBERBOSS: Form jQuery object:', $form);
            console.log('🔥 CHAMBERBOSS: Handler function:', this.handleMemberRegistration);
            
            $form.on('submit', this.handleMemberRegistration.bind(this));
            console.log('🔥 CHAMBERBOSS: Form submit handler attached successfully');
            
            // Test the handler attachment
            console.log('🔥 CHAMBERBOSS: Testing form submit handler...');
            $form.off('submit.test').on('submit.test', function(e) {
                console.log('🔥 CHAMBERBOSS: TEST HANDLER TRIGGERED - Form submit is working!');
            });
            
            // Add test button for debugging
            if ($form.length) {
                $form.append('<button type="button" id="test-ajax" style="margin: 10px; background: orange; color: white; padding: 5px;">🔧 TEST AJAX</button>');
                $('#test-ajax').on('click', function() {
                    console.log('🔧 CHAMBERBOSS: Test AJAX button clicked');
                    $.post(chamberboss_frontend.ajax_url, {
                        action: 'chamberboss_test_ajax'
                    }, function(response) {
                        console.log('🔧 CHAMBERBOSS: Test AJAX success:', response);
                    }).fail(function(xhr, status, error) {
                        console.log('🔧 CHAMBERBOSS: Test AJAX error:', xhr, status, error);
                    });
                });
            }
        },
        
        /**
         * Initialize Stripe
         */
        initStripe: function() {
            console.log('Chamberboss: initStripe called');
            console.log('Chamberboss: window.Stripe available:', !!window.Stripe);
            console.log('Chamberboss: chamberboss_frontend object:', chamberboss_frontend);
            console.log('Chamberboss: Stripe publishable key:', chamberboss_frontend.stripe_publishable_key);

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
                console.log('Chamberboss: Stripe instance created:', !!this.stripe);

                // Create Payment Intent and Elements
                this.createPaymentIntent();

                console.log('Chamberboss: Stripe initialization completed successfully');
                return true;
            } catch (error) {
                console.error('Chamberboss: Stripe initialization failed:', error);
                return false;
            }
        },

                        /**
                 * Create Payment Intent and initialize Elements
                 */
                createPaymentIntent: function() {
                    console.log('🔧 CHAMBERBOSS: Creating payment intent...');
                    console.log('🔧 CHAMBERBOSS: AJAX URL:', chamberboss_frontend.ajax_url);
                    console.log('🔧 CHAMBERBOSS: Nonce:', chamberboss_frontend.nonce);
                    
                    // Make AJAX call to create payment intent
                    var ajaxData = {
                        action: 'chamberboss_create_payment_intent',
                        nonce: chamberboss_frontend.nonce
                    };
                    console.log('🔧 CHAMBERBOSS: AJAX data being sent:', ajaxData);
                    
                    $.post(chamberboss_frontend.ajax_url, ajaxData)
                    .done(function(response) {
                        console.log('🔧 CHAMBERBOSS: Payment intent response:', response);
                        console.log('🔧 CHAMBERBOSS: Response type:', typeof response);
                        console.log('🔧 CHAMBERBOSS: Response.success:', response.success);
                        console.log('🔧 CHAMBERBOSS: Response.data:', response.data);
                        
                        if (response && response.success && response.data && response.data.clientSecret) {
                            console.log('🔧 CHAMBERBOSS: Payment intent created successfully');
                            console.log('🔧 CHAMBERBOSS: Client secret:', response.data.clientSecret);
                            console.log('🔧 CHAMBERBOSS: Payment intent ID:', response.data.paymentIntentId);
                            this.paymentIntentId = response.data.paymentIntentId;
                            this.initializeElements(response.data.clientSecret);
                        } else {
                            console.error('🔧 CHAMBERBOSS: Failed to create payment intent - response analysis:');
                            console.error('🔧 CHAMBERBOSS: - Raw response:', response);
                            console.error('🔧 CHAMBERBOSS: - Response exists:', !!response);
                            console.error('🔧 CHAMBERBOSS: - Response.success:', response ? response.success : 'N/A');
                            console.error('🔧 CHAMBERBOSS: - Response.data:', response ? response.data : 'N/A');
                            console.error('🔧 CHAMBERBOSS: - ClientSecret exists:', !!(response && response.data && response.data.clientSecret));
                            if (response && response.data && response.data.message) {
                                console.error('🔧 CHAMBERBOSS: - Error message:', response.data.message);
                            }
                            // Continue without payment elements for free memberships
                        }
                    }.bind(this))
                    .fail(function(xhr, status, error) {
                        console.error('🔧 CHAMBERBOSS: Payment intent AJAX failed:');
                        console.error('🔧 CHAMBERBOSS: XHR:', xhr);
                        console.error('🔧 CHAMBERBOSS: Status:', status);  
                        console.error('🔧 CHAMBERBOSS: Error:', error);
                        console.error('🔧 CHAMBERBOSS: Response text:', xhr.responseText);
                        console.error('🔧 CHAMBERBOSS: HTTP Status:', xhr.status);
                        console.error('🔧 CHAMBERBOSS: Ready State:', xhr.readyState);
                        
                        // Try to parse the response as JSON to see what we got
                        try {
                            var jsonResponse = JSON.parse(xhr.responseText);
                            console.error('🔧 CHAMBERBOSS: Parsed JSON response:', jsonResponse);
                        } catch (parseError) {
                            console.error('🔧 CHAMBERBOSS: Response is not valid JSON:', parseError);
                        }
                        
                        // Continue without payment elements for free memberships
                    });
                },

        /**
         * Initialize Stripe Elements with client secret
         */
        initializeElements: function(clientSecret) {
            console.log('🔧 CHAMBERBOSS: Initializing Stripe Elements with clientSecret:', clientSecret);
            
            try {
                // Create Elements instance
                this.elements = this.stripe.elements({
                    clientSecret: clientSecret
                });
                console.log('🔧 CHAMBERBOSS: Elements instance created:', !!this.elements);

                // Create Payment Element
                this.paymentElement = this.elements.create('payment');
                console.log('🔧 CHAMBERBOSS: Payment element created:', !!this.paymentElement);

                // Mount Payment Element
                const paymentElementDiv = document.getElementById('payment-element');
                if (paymentElementDiv) {
                    this.paymentElement.mount('#payment-element');
                    console.log('🔧 CHAMBERBOSS: Payment element mounted successfully');
                } else {
                    console.error('🔧 CHAMBERBOSS: Payment element div not found');
                }
            } catch (error) {
                console.error('🔧 CHAMBERBOSS: Elements initialization failed:', error);
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
            console.log('🔥🔥🔥 CHAMBERBOSS: FORM SUBMISSION HANDLER TRIGGERED! 🔥🔥🔥');
            console.log('🔥 CHAMBERBOSS: Event object:', e);
            console.log('🔥 CHAMBERBOSS: Form element:', e.target);
            console.log('🔥 CHAMBERBOSS: Preventing default submission...');
            
            e.preventDefault();
            
            var $form = $(e.target);
            var $submitButton = $form.find('button[type="submit"]');
            var $messages = $('#registration-messages');
            var self = this;
            
            console.log('🔧 CHAMBERBOSS: Form elements found - Form:', $form.length, 'Button:', $submitButton.length, 'Messages:', $messages.length);
            
            // Validate required fields first
            var memberName = $form.find('[name="member_name"]').val().trim();
            var memberEmail = $form.find('[name="member_email"]').val().trim();
            
            console.log('🔧 CHAMBERBOSS: Form validation - Name:', memberName ? 'OK' : 'MISSING', 'Email:', memberEmail ? 'OK' : 'MISSING');
            
            if (!memberName || !memberEmail) {
                console.log('🔧 CHAMBERBOSS: Form validation failed - missing required fields');
                $messages.html('<div class="form-message error">Please fill in all required fields.</div>');
                return;
            }
            
            // Check if payment is required
            var hasPaymentElement = $form.find('#payment-element').length > 0;
            var requiresPayment = hasPaymentElement && this.stripe && this.paymentElement;
            
            console.log('🔧 CHAMBERBOSS: === PAYMENT FLOW DECISION ===');
            console.log('🔧 CHAMBERBOSS: hasPaymentElement (DOM check):', hasPaymentElement);
            console.log('🔧 CHAMBERBOSS: this.stripe (instance exists):', !!this.stripe);
            console.log('🔧 CHAMBERBOSS: this.paymentElement (element mounted):', !!this.paymentElement);
            console.log('🔧 CHAMBERBOSS: requiresPayment (final decision):', requiresPayment);
            console.log('🔧 CHAMBERBOSS: === END PAYMENT FLOW DECISION ===');
            
            if (requiresPayment) {
                console.log('🔧 CHAMBERBOSS: ✅ Using payment flow');
                this.processPaymentAndRegistration($form, $submitButton, $messages);
            } else {
                console.log('🔧 CHAMBERBOSS: ❌ Using direct registration (no payment)');
                console.log('🔧 CHAMBERBOSS: This will cause payment_intent_id_missing error');
                this.submitRegistration($form, $submitButton, $messages);
            }
        },
        
        /**
         * Process payment and registration
         */
        processPaymentAndRegistration: function($form, $submitButton, $messages) {
            var self = this;
            
            console.log('Chamberboss: Starting payment and registration process');
            
            // First, create payment intent
            this.createPaymentIntent($form, function(clientSecret, paymentIntentId) {
                if (!clientSecret) {
                    $messages.html('<div class="form-message error">Failed to initialize payment</div>');
                    self.resetForm($form, $submitButton);
                    return;
                }
                
                console.log('Chamberboss: Payment intent created, initializing elements');
                self.paymentIntentId = paymentIntentId;
                
                // Now create elements with the client secret
                self.elements = self.stripe.elements({
                    clientSecret: clientSecret
                });
                
                // Create and mount payment element
                self.paymentElement = self.elements.create('payment');
                self.paymentElement.mount('#payment-element');
                
                console.log('Chamberboss: Payment element mounted, ready for payment');
                
                // Update button text
                $submitButton.html('Complete Payment');
                
                // Handle payment element changes
                self.paymentElement.on('change', function(event) {
                    if (event.error) {
                        console.log('Chamberboss: Payment element error:', event.error);
                        $messages.html('<div class="form-message error">' + event.error.message + '</div>');
                    } else {
                        $messages.empty();
                    }
                });
                
                // Show payment UI and wait for user to complete payment
                $messages.html('<div class="form-message info">Please complete your payment details above, then click "Complete Payment".</div>');
                self.setFormLoading($form, false);
                $submitButton.prop('disabled', false);
                
                // Add click handler for completing payment
                $submitButton.off('click.payment').on('click.payment', function(e) {
                    e.preventDefault();
                    self.confirmPayment($form, $submitButton, $messages, clientSecret);
                });
            });
        },
        
        /**
         * Confirm payment after user completes details
         */
        confirmPayment: function($form, $submitButton, $messages, clientSecret) {
            var self = this;
            
            console.log('Chamberboss: Confirming payment');
            
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
                    console.log('Chamberboss: Payment error:', result.error);
                    $messages.html('<div class="form-message error">' + result.error.message + '</div>');
                    self.resetForm($form, $submitButton);
                } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    console.log('Chamberboss: Payment succeeded, submitting registration');
                    // Payment successful, now submit registration
                    self.submitRegistration($form, $submitButton, $messages, result.paymentIntent.id);
                } else {
                    console.log('Chamberboss: Unexpected payment result:', result);
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
         * Create payment intent
         */
        createPaymentIntent: function($form, callback) {
            var formData = new FormData($form[0]);
            formData.append('action', 'chamberboss_create_payment_intent');
            formData.append('nonce', $form.find('[name="registration_nonce"]').val());
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        callback(response.data.client_secret, response.data.payment_intent_id);
                    } else {
                        callback(null, null);
                    }
                },
                error: function() {
                    callback(null, null);
                }
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
            
            console.log('Chamberboss: Making registration AJAX call to:', chamberboss_frontend.ajax_url);
            console.log('Chamberboss: FormData contents:', Array.from(formData.entries()));
            
            $.ajax({
                url: chamberboss_frontend.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    console.log('🔧 CHAMBERBOSS: AJAX request starting');
                },
                success: function(response) {
                    console.log('🔧 CHAMBERBOSS: AJAX SUCCESS callback triggered');
                    console.log('🔧 CHAMBERBOSS: Raw response:', response);
                    console.log('🔧 CHAMBERBOSS: Response type:', typeof response);
                    console.log('🔧 CHAMBERBOSS: Response.success:', response.success);
                    console.log('🔧 CHAMBERBOSS: Response.data:', response.data);
                    
                    // Try to parse response if it's a string
                    if (typeof response === 'string') {
                        console.log('🔧 CHAMBERBOSS: Response is string, attempting to parse JSON');
                        try {
                            response = JSON.parse(response);
                            console.log('🔧 CHAMBERBOSS: Parsed response:', response);
                        } catch (e) {
                            console.error('🔧 CHAMBERBOSS: Failed to parse JSON:', e);
                            console.log('🔧 CHAMBERBOSS: Raw string content:', response);
                        }
                    }
                    
                    if (response && response.success) {
                        console.log('🔧 CHAMBERBOSS: Processing success response');
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
                    console.log('🔧 CHAMBERBOSS: AJAX ERROR callback triggered');
                    console.log('🔧 CHAMBERBOSS: XHR object:', xhr);
                    console.log('🔧 CHAMBERBOSS: Status:', status);
                    console.log('🔧 CHAMBERBOSS: Error:', error);
                    console.log('🔧 CHAMBERBOSS: Response text:', xhr.responseText);
                    
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
        }
    };
    
                // Initialize when document is ready
            $(document).ready(function() {
                console.log('🔥🔥🔥 CHAMBERBOSS v1.0.7: DOCUMENT READY HANDLER CALLED! 🔥🔥🔥');
        console.log('🔥 CHAMBERBOSS: Document ready state:', document.readyState);
        console.log('🔥 CHAMBERBOSS: Frontend data available:', typeof chamberboss_frontend !== 'undefined' ? 'YES' : 'NO');
        console.log('🔥 CHAMBERBOSS: jQuery available:', !!window.jQuery);
        
        if (typeof chamberboss_frontend !== 'undefined') {
            console.log('🔥 CHAMBERBOSS: AJAX URL:', chamberboss_frontend.ajax_url);
            console.log('🔥 CHAMBERBOSS: Stripe key available:', !!chamberboss_frontend.stripe_publishable_key);
        }
        
                        console.log('🔥🔥🔥 CHAMBERBOSS v1.0.7: CALLING MAIN INIT FUNCTION... 🔥🔥🔥');
                Chamberboss.init();
                console.log('🔥🔥🔥 CHAMBERBOSS v1.0.7: DOCUMENT READY COMPLETE! 🔥🔥🔥');
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

