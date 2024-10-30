/**
 * @file Handle all Printeers product updates 
 * @author Mike Sies <support@printeers.com>
 * @version 1.0
 */

jQuery(document).ready(function ($) {

	/**
	 * Holds an array of all available actions and their parameters
	 */
    var productType = [];
    var actionArray = [];
    var progress = 0;
    var posted = 0;

	/**
	 * Process the response of the actions worker
	 * 
	 * @param {Object} response 
	 */
    function processResponse(response) {

        switch (response["responseType"]) {

            // Worker provided us with actions to display
            case 'actions':
                buildActionsTable(response["responseContent"]["actions"]);
                $("#loading").hide();
                break;

            // Something went wrong getting the actions
            case 'error':
                console.log(response);
                displayError(response["responseContent"]["error"]);
                $("#loading").hide();
                break;

            // Worker started executing a row
            case 'processing':
                showProcessing(response["responseContent"]);
                break;

            // Worker did an action and this is the result
            case 'processed':
                displayResult(response["responseContent"]);
                updateProgressbar();
                break;

            // Worker is done
            case 'finished':
                endProgress();
                break;

        }

    }

	/**
	 * Fill the empty actions table with all results from the actions worker
	 * 
	 * @param {Object} actions 
	 */
    function buildActionsTable(actions) {

        if (actions.length < 1) {
            $('#accordion').replaceWith(
                $('<p>').text('No actions found.'),
            )
            return;
        }

        productType['simple'] = '<span style="color: #00b1e2;">Simple product</span>';
        productType['variable'] = '<span style="color: #780d16;">Variable product</span>';

        var row = 0;
        $.each(actions, function (x, product) {
            $.each(product["actions"], function (i, action) {
                row++;

                // Add everything to an array for posting
                actionArray[row] = {
                    "row": row,
                    "subject": action.subject,
                    "variable_product": action.variable_product,
                    "type": action.type,
                    "arguments": JSON.stringify(action.arguments)
                }

                if (!$('#variable_product' + action.variable_product).length) {

                    $('#accordion').append(
                        $('<table>').append(
                            $('<tr>').append(
                                $('<td>').html('<input class="cb-select-all" value="' + action.variable_product + '" type="checkbox" />'),
                                $('<td>').html(productType[product.product_type]),
                                $('<td>').html(product.name + ' (' + (product["actions"]).length + ' actions available)'),
                            )
                        )
                    ).appendTo('#accordion');

                    $('<div>').append(
                        $('<table id="variable_product' + action.variable_product + '" class="widefat fixed striped">').append(
                            $('<thead>').append(
                                $('<tr>').append(
                                    $('<th>').html(''),
                                    $('<th>').text('#'),
                                    $('<th>').text('Action'),
                                    $('<th>').text(''),
                                )
                            )
                        )
                    ).appendTo('#accordion');
                }

                // Make the table
                var checkbox = '<input id="cb-select-' + row + '" type="checkbox" name="action" value="' + row + '"></input>';
                var result = '<div id="result_' + row + '"></div>';
                var $tr = $('<tr>').append(
                    $('<td>').html(checkbox),
                    $('<td>').html('<p>' + row + '</p>'),
                    $('<td>').html('<p>' + action.explain + '</p>'),
                    $('<td>').html(result)
                ).appendTo('#variable_product' + action.variable_product);
            });
        });

        // Select all checkboxes for a single product
        $('.cb-select-all').on('change', function () {
            $('td input:checkbox', '#variable_product' + this.value).prop('checked', this.checked);
        });

        // Select all checkboxes for all products
        $('.cb-select-all-products').on('change', function () {
            $('input:checkbox').prop('checked', this.checked);
        });

        // Change the checkbox counter on every click
        $('input:checkbox').on('change', function () {
            var qtyselected = ($('input:checkbox:checked').not('.cb-select-all').not('.cb-select-all-products').length);
            $('.qtyselected').text(qtyselected);
        });

        // Create jQuery Accordion
        $("#accordion").accordion({
            collapsible: true, active: false, heightStyle: "content", icons: false
        });

        // Make sure select all checkboxes still work
        $('#accordion input[type="checkbox"]').click(function (e) {
            e.stopPropagation();
        });
    }

    /**
     * Display returned errors
     * 
     * @param {Object} response 
     */
    function displayError(response) {
        $("#error").show();
        $("#error").html('<p>' + response + '</p>');
    }

    /**
     * Shows a loading webm in the active row
     * 
     * @param {Object} response 
     */
    function showProcessing(response) {

        $("#result_" + response["row"]).html('<img src="' + print_and_ship.imgURL + 'loading.apng" />');
        $('.qtyselected').text('0');
    }

    /**
     * Display the result of every processed action in the right table row
     * 
     * @param {Object} response 
     */
    function displayResult(response) {
        $("#result_" + response["row"]).html("<p>" + response["message"] + "</p>");
        $('.qtyselected').text('0');
    }

    /**
     * Update the progressbar to show result
     */
    function updateProgressbar() {
        progress++;
        var percent = Math.round(progress / posted * 100);
        $('#progress p').html(percent + '% (' + progress + '/' + posted + ')');
        $('progress').val(percent);
    }

    /**
     * Start the progress, disable everything and display progressbar
     */
    function startProgress() {
        $('table').find('input:checkbox:checked').remove();
        $('.tablenav').hide();
        $('#progress').show();
    }

    /**
     * Stop the progress and restore functionality
     */
    function endProgress() {
        setTimeout(function () { // Wait a second, we want the user to see the 100% progress
            posted = 0;
            progress = 0;

            $('.tablenav').show();
            $('#progress').hide();
            $('#progress p').html('0%');
            $('progress').val('0');
        }, 1000);
    }

    // Start the worker and listen for responses
    worker = new Worker(print_and_ship.jsWorker);
    worker.addEventListener('message', function (e) {
        processResponse(e.data);
    });

    // Load actions with the worker
    worker.postMessage({ 'do': 'loadActions', 'nonce': wpApiSettings.nonce, 'url': print_and_ship.discoverURL });

    // Process the actions on form submit
    $("#actions_form").submit(function (event) {
        event.preventDefault();
        var post_values = $("#actions_form").serializeArray();

        // Add the action data to the post
        var post_actions = [];
        post_values.forEach(function (post_value) {
            post_actions.push(actionArray[post_value["value"]]);
            posted++;
        });

        // Disable everything during processing and show progress to user
        startProgress();

        // Do the posted actions 
        worker.postMessage({ 'do': 'executeActions', 'nonce': wpApiSettings.nonce, 'url': print_and_ship.executeURL, 'actions': post_actions });

    });

});
