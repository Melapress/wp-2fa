import { browserSupportsWebAuthn, startRegistration } from "./index.js";

/**
 * Create Passkey Registration.
 */
async function createRegistration(event, isUsb = false ) {
	event.preventDefault();
	let attResp;

	const registerButton = event.target;
	const nonce = registerButton.dataset.nonce;

	try {
		const response = await jQuery.post(
			wp2faData.ajaxURL,
			{
				"_wpnonce": nonce,
				"action": "wp2fa_profile_register",
				'is_usb': isUsb,
			},);

		attResp = await startRegistration(response.data);
	} catch (error) {
		throw error;
	}

	const passkeyName = await openPrompt();

	// POST the response to the endpoint that calls.
	try {
		const response = await jQuery.post(
			wp2faData.ajaxURL,
			{
				"_wpnonce": nonce,
				"action": "wp2fa_profile_response",
				"data": attResp,
				'passkey_name': passkeyName,
			},);

		if (response.success === true) {
			window.location.reload();
		}
	} catch (error) {
		throw error;
	}
}

/**
 * Passkey Registration Handler.
 */
wp.domReady(() => {
	const registerButton = document.querySelector('.wp-2fa-register-new-passkey');
	const registerMessage = document.querySelector('.wp-register-passkey--message');
	const registerUsbButton = document.querySelector( '.wp-2fa-register-new-usbpasskey' );

	if (!registerButton || !registerMessage) {
		return;
	}

	// Hide register button if browser doesn't support WebAuthn.
	if (!browserSupportsWebAuthn()) {
		registerButton.style.display = 'none';
		return;
	}

	registerButton.addEventListener('click', async (event) => {
		try {
			await createRegistration(event, false);
		} catch (error) {
			// Some basic error handling
			if (error.name === 'InvalidStateError') {
				registerMessage.innerText = wp.i18n.__(
					'Error: Authenticator was probably already registered by you',
					'wp-2fa',
				);
			} else {
				registerMessage.innerText = `Error: ${error.message}`;
			}
			registerMessage.classList.add('error');
		}
	});

	registerUsbButton.addEventListener( 'click', async (event) => {
		try {
			await createRegistration( event, true );
		} catch ( error ) {
			// Some basic error handling
			if ( error.name === 'InvalidStateError' ) {
				registerMessage.innerText = wp.i18n.__(
					'Error: Authenticator was probably already registered by you',
					'wp-2fa',
				);
			} else {
				registerMessage.innerText = `Error: ${ error.message }`;
			}
			registerMessage.classList.add( 'error' );
		}
	} );
});

/**
 * Revoke Passkey.
 *
 * @param {Event} event The event.
 */
async function revokePasskey(event) {
	event.preventDefault();

	if (
		// eslint-disable-next-line no-alert
		!window.confirm(
			wp.i18n.__('Are you sure you want to revoke this passkey? This action cannot be undone.', 'wp-2fa'),
		)
	) {
		return;
	}

	const revokeButton = event.target;
	const fingerprint = revokeButton.dataset.id;
	const nonce = revokeButton.dataset.nonce;
	const user_id = revokeButton.dataset.userid;

	try {
		const response = await jQuery.post(
			wp2faData.ajaxURL,
			{
				"_wpnonce": nonce,
				"user_id": user_id,
				"fingerprint": fingerprint,
				"action": "wp2fa_profile_revoke_key",
			},);

		if (response.success === true) {
			window.location.reload();
		}
	} catch (error) {
		throw error;
	}
}

/**
 * Enable/Disable Passkey.
 *
 * @param {Event} event The event.
 */
async function enableDisablePasskey(event) {
	event.preventDefault();

	const enableButton = event.target;
	const fingerprint = enableButton.dataset.id;
	const nonce = enableButton.dataset.nonce;
	const user_id = enableButton.dataset.userid;

	try {
		const response = await jQuery.post(
			wp2faData.ajaxURL,
			{
				"_wpnonce": nonce,
				"user_id": user_id,
				"fingerprint": fingerprint,
				"action": "wp2fa_profile_enable_key",
			},);

		if (response.success === true) {
			window.location.reload();
		}
	} catch (error) {
		throw error;
	}
}

/**
 * Passkey Revoke handler.
 */
wp.domReady(() => {
	const revokeButtons = document.querySelectorAll('.wp-2fa-passkey-list-table button.delete');

	if ( revokeButtons ) {
			
		revokeButtons.forEach(revokeButton => {
			revokeButton.addEventListener('click', revokePasskey);
		});

	}
	const enableButtons = document.querySelectorAll('.wp-2fa-passkey-list-table button.disable');

	if ( enableButtons ) {
			
		enableButtons.forEach( enableButtons => {
			enableButtons.addEventListener('click', enableDisablePasskey);
		});

	}

});

  const overlay = document.getElementById("overlay");
  const customPrompt = document.getElementById("customPrompt");
  const submitBtn = document.getElementById("submitBtn");
  const userInput = document.getElementById("userInput");
  const errorDiv = document.getElementById("error");

  // Unicode-safe regex: letters (any language), digits, dash, underscore, space
  const validPattern = /^[\p{L}\p{N}\-_ ]+$/u;
// Core async function
  function openPrompt() {
    return new Promise((resolve) => {
		const registerButton = document.querySelector('.wp-2fa-register-new-passkey');
		const registerUsbButton = document.querySelector( '.wp-2fa-register-new-usbpasskey' );

		registerButton.disabled = true;
		registerUsbButton.disabled = true;

      overlay.style.display = "flex";
      userInput.value = "";
      userInput.focus();
      errorDiv.textContent = "";

      function handleSubmit(e) {
		e.cancelBubble = true;
		e.preventDefault();
		e.stopPropagation();
        const value = userInput.value.trim();

        if (!value) {
          errorDiv.textContent = "Input cannot be empty.";
          return;
        }

        if (!validateInput(value)) {
          errorDiv.textContent = "Only letters, numbers, dashes, underscores, and spaces allowed.";
          return;
        }

        // Clean up
        overlay.style.display = "none";
        userInput.removeEventListener("keypress", handleKeypress);
        submitBtn.removeEventListener("click", handleSubmit);

        resolve(value);
      }

      function handleKeypress(e) {
		e.cancelBubble = true;
		e.stopPropagation();
        if (e.key === "Enter") {
			e.stopPropagation();
			handleSubmit(e);
		}
		if (e.key === "Escape") {
			overlay.style.display = "none";
			userInput.removeEventListener("keypress", handleKeypress);
        	submitBtn.removeEventListener("click", handleSubmit);
		}
      }

      submitBtn.addEventListener("click", handleSubmit);
      userInput.addEventListener("keypress", handleKeypress);
	  
	  customPrompt.addEventListener("keydown", (e) => {
		e.stopPropagation();
		if (e.key === "Escape") {
			overlay.style.display = "none";
			userInput.removeEventListener("keypress", handleKeypress);
			submitBtn.removeEventListener("click", handleSubmit);


			registerButton.disabled = false;
			registerUsbButton.disabled = false;
		}
		});
    });
  }

  function validateInput(value) {
    return validPattern.test(value);
  }

//   function handleSubmit(e) {
// 	e.preventDefault();
//     const value = userInput.value.trim();

//     if (!value) {
//       errorDiv.textContent = "Input cannot be empty.";
//       return;
//     }

//     if (!validateInput(value)) {
//       errorDiv.textContent = "Only letters, numbers, dashes, underscores, and spaces allowed.";
//       return;
//     }

//     // Valid input
//     alert("You entered: " + value);
//     overlay.style.display = "none";
//     userInput.value = "";
//   }


//   submitBtn.addEventListener("click", handleSubmit);

//   userInput.addEventListener("keypress", (e) => {
//     if (e.key === "Enter") handleSubmit();
//   });
/**
 * Passkey Revoke handler.
 */
wp.domReady( () => {
	const revokeButtons = document.querySelectorAll( '.wp-2fa-passkey-list-table button.delete' );

	if (revokeButtons) {

		revokeButtons.forEach(revokeButton => {
			revokeButton.addEventListener('click', revokePasskey);
		});

	}
	const enableButtons = document.querySelectorAll('.wp-2fa-passkey-list-table button.disable');

	if (enableButtons) {

		enableButtons.forEach(enableButtons => {
			enableButtons.addEventListener('click', enableDisablePasskey);
		});

	}

});
