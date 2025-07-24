jQuery(document).ready(function($) {
    // Handle member signup form submission
    $('.cb-membership-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitButton = form.find('input[type="submit"]');
        var originalText = submitButton.val();
        
        // Show loading state
        submitButton.val('Processing...').prop('disabled', true);
        
        // Submit form via AJAX
        $.post(form.attr('action'), form.serialize(), function(response) {
            if (response.success) {
                // Show success message
                alert('Membership signup processed successfully!');
                // Redirect or update UI as needed
                form[0].reset();
            } else {
                // Show error message
                alert('Error processing membership: ' + response.data);
            }
        }).fail(function() {
            alert('An error occurred. Please try again.');
        }).always(function() {
            // Reset button
            submitButton.val(originalText).prop('disabled', false);
        });
    });
    
    // Example: Add confirmation for delete actions
    $('.cb-delete-listing').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this listing?')) {
            e.preventDefault();
        }
    });
});