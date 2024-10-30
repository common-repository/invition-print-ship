/**
 * @file JS Worker for processing Printeers product updates
 * @author Mike Sies <support@printeers.com>
 * @version 1.0
 */

/**
 * Same as forEach but awaiting callback for asynchronous processing
 * 
 * @param {Array} array 
 * @param {Array} callback 
 */
async function asyncForEach(array, callback) {
    for (let index = 0; index < array.length; index++) {
        await callback(array[index], index, array);
    }
}

/**
 * Processing was finished
 */
function returnFinished() {
    var response = {
        "responseType": "finished"
    }
    self.postMessage(response);
}

/**
 * Which item is currently processing?
 * 
 * @param {int} subject 
 */
function returnProcessing(subject) {
    var response = {
        "responseType": "processing",
        "responseContent": { "row": subject }
    }
    self.postMessage(response);
}

/**
 * An error was received
 */
function returnErrors() {
    var response = {
        "responseType": "error",
        "responseContent": { "row": subject }
    }
    self.postMessage(response);
}

/**
 * Load all the available actions through Ajax request
 * 
 * @param {String} url 
 * @param {String} nonce 
 */
function loadActions(url, nonce) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, false);
    xhr.setRequestHeader('X-WP-Nonce', nonce);
    xhr.onload = function () {
        if (xhr.status !== 200) {
            console.log("Discover actions request failed: " + xhr.statusText);
        }

        xhrResult = JSON.parse(xhr.response);

        if (xhrResult['actions']) { // We received actions
            var answer = {
                "responseType": "actions",
                "responseContent": xhrResult,
            }

        } else if (xhrResult[0]['error']) { // We received errors
            var answer = {
                "responseType": "error",
                "responseContent": xhrResult[0],
            }

        } else { // Response is invalid
            var answer = {
                "responseType": "error",
                "responseContent": { "error": "Plugin returned an invalid response. Please check your logs for more info" },
            }
            console.log('Invalid response received: ' + xhrResult);
        }

        self.postMessage(answer);

    };
    xhr.send();
}

/**
 * Execute all requested actions asynchronously through Ajax posts
 * 
 * @param {String} url 
 * @param {String} nonce 
 * @param {Array} actions 
 */
function executeActions(url, nonce, actions) {

    const execute = async () => {
        await asyncForEach(actions, async (action) => {

            returnProcessing(action["row"]);

            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    var answer = {
                        "responseType": "processed",
                        "responseContent": JSON.parse(xhr.responseText),
                    }
                    self.postMessage(answer);
                }
            }

            xhr.open('POST', url, false);
            xhr.setRequestHeader('X-WP-Nonce', nonce);
            xhr.setRequestHeader("Content-Type", "application/json");
            xhr.send(JSON.stringify(action));
        });

        returnFinished();
    }

    execute();

}

/**
 * Select the right function to process the request
 */
self.addEventListener('message', function (e) {
    var data = e.data;
    switch (data.do) {
        case 'loadActions':
            self.loadActions(data.url, data.nonce);
            break;
        case 'executeActions':
            self.executeActions(data.url, data.nonce, data.actions);
            break;
    };
}, false);
