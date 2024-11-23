jQuery(document).ready(function ($) {
    let selectedDonationAmount = $('#donation_value').length>0 ? parseFloat($('#donation_value').val()) : null;
    let currentDonationFee = $('#donation_value').length>0 ? parseFloat($('#donation_value').val()) : null;
    let isOtherSelected = false; // Track if "Other" is selected
    let isRoundUpSelected = false; // Track if "Round-Up" is selected

    // Handle fixed donation buttons
    $(document).on('click', '.youbehero-donation-btn:not(#youbehero-other)', function (e) {
        e.preventDefault();

        const clickedButton = $(this);
        const amount = clickedButton.data('amount');
        isOtherSelected = false;
        isRoundUpSelected = false;

        if (clickedButton.hasClass('selected')) {
            resetSelection();
        } else {
            resetSelection();
            clickedButton.addClass('selected');
            selectedDonationAmount = amount;
            updateDonationFee(amount); // Trigger the AJAX request to add the fee
        }
    });

    // Handle "Other" button
    $(document).on('click', '#youbehero-other', function (e) {
        e.preventDefault();
        console.log('#youbehero-other');

        const clickedButton = $(this);
        if (clickedButton.hasClass('selected')) {
            $('.youbehero-other-input').fadeOut();
            resetSelection();
        } else {
            resetSelection();
            clickedButton.addClass('selected');
            isOtherSelected = true;
            isRoundUpSelected = false;
            $('.youbehero-donation-buttons').hide(); // Hide donation buttons
            $('.youbehero-other-input').show(); // Show the custom input section
        }
    });

    // Handle Round-Up button
    $(document).on('click', '#youbehero-round-up', function (e) {
        e.preventDefault();

        isRoundUpSelected = true;
        isOtherSelected = true; // "Other" is part of the form

        const roundUpValue = $(this).data('price'); // Dynamically calculate the round-up value
        selectedDonationAmount = roundUpValue;

        // Set the round-up value in the input field
        $('#youbehero-custom-donation').val(roundUpValue);

        // Ensure the "Other" form stays visible
        $('.youbehero-other-input').show();
        $('.youbehero-donation-buttons').hide();

        // Mark "Other" button as active
        $('#youbehero-other').addClass('selected');

        // Send the round-up fee to the server
        updateDonationFee(roundUpValue);
    });

    // Handle custom donation input
    $(document).on('input', '#youbehero-custom-donation', function () {
        const customAmount = $(this).val();
        selectedDonationAmount = customAmount;
        updateDonationFee(customAmount); // Trigger the AJAX request to update the fee
    });

    // Reapply the active state after WooCommerce checkout updates
    $(document.body).on('updated_checkout', function () {

        // Initialize SelectWoo for the organization dropdown
        $('#youbehero-organization-select').selectWoo({
            templateResult: formatOrganization, // Format the options in the dropdown
            templateSelection: formatSelectedOrganization, // Format the selected option
            minimumResultsForSearch: -1, // Disable the search bar
            placeholder: 'Select an organization', // Placeholder text
        });

        if (isRoundUpSelected || isOtherSelected) {
            $('.youbehero-donation-buttons').hide();
            $('.youbehero-other-input').show();

            if (isRoundUpSelected) {
                $('#youbehero-other').addClass('selected');
                const roundUpValue = parseFloat($('#youbehero-round-up').data('price')) || 0;
                $('#youbehero-custom-donation').val(roundUpValue); // Reapply round-up value
            }
        } else {
            $('.youbehero-donation-buttons').show();
            $('.youbehero-other-input').hide();
        }


        if (selectedDonationAmount) {
            if (selectedDonationAmount === 'other') {
                $('#youbehero-other').addClass('selected');
            } else {
                $(`.youbehero-donation-btn[data-amount="${selectedDonationAmount}"]`).addClass('selected');
            }
        }
    });

    // Reset all selections
    function resetSelection() {
        $('.youbehero-donation-btn').removeClass('selected'); // Reset all buttons
        $('.youbehero-other-input').hide(); // Hide the custom input form
        $('.youbehero-donation-buttons').show(); // Show the donation buttons
        selectedDonationAmount = null; // Clear the selected donation amount
        isRoundUpSelected = false;
        isOtherSelected = false;
        updateDonationFee(0); // Reset the donation fee via AJAX
    }

    // AJAX request to update the donation fee
    function updateDonationFee(amount) {
        const parsedAmount = parseFloat(amount); // Ensure the amount is a number

        // Treat null as 0 and skip if the fee is the same
        if ((currentDonationFee === null ? 0 : currentDonationFee) === parsedAmount) {
            console.log('Donation fee is already applied:', currentDonationFee === null ? 0 : currentDonationFee);
            return; // Skip the AJAX request
        }

        // Store the new fee value locally
        currentDonationFee = parsedAmount;

        $.ajax({
            url: youbehero_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'update_donation_fee',
                amount: amount,
            },
            success: function (response) {
                if (response.success) {
                    $('body').trigger('update_checkout');
                }
            },
            error: function (xhr, status, error) {
                console.error('Failed to update donation fee:', error);
            },
        });
    }

    // Function to format the dropdown options
    function formatOrganization(option) {
        if (!option.id) {
            return option.text; // Default text for placeholders
        }

        const image = $(option.element).data('image'); // Get the image from the option's data attribute
        const text = option.text; // Get the option text

        // Return custom HTML for the dropdown
        return $(
            '<div class="youbehero-organization-option">' +
            '<img src="' + image + '" alt="' + text + '" style="width: 20px; height: 20px; margin-right: 8px;" />' +
            '<span>' + text + '</span>' +
            '</div>'
        );
    }

    // Function to format the selected option
    function formatSelectedOrganization(option) {
        if (!option.id) {
            return 'Select an organization'; // Default placeholder text
        }

        const image = $(option.element).data('image'); // Get the image from the option's data attribute
        const text = option.text; // Get the option text

        // Return custom HTML for the selected option
        return $(
            '<div class="youbehero-selected-organization">' +
            '<img src="' + image + '" alt="' + text + '" style="width: 20px; height: 20px; margin-right: 8px;" />' +
            '<span>' + text + '</span>' +
            '</div>'
        );
    }
});
