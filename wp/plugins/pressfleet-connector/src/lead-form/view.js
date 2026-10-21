import { getContext, getElement, store } from '@wordpress/interactivity';

/**
 * Sends the form without a page reload. The server always answers quickly: the request is stored on this site and
 * forwarded to the hub in the background.
 */
store( 'pressfleet/lead-form', {
	actions: {
		*submit( event ) {
			event.preventDefault();
			const context = getContext();
			const { ref } = getElement();
			context.status = 'sending';
			context.error = '';
			try {
				const response = yield fetch( context.endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( Object.fromEntries( new FormData( ref ) ) ),
				} );
				const body = yield response.json();
				if ( ! response.ok ) {
					throw new Error( body.message || context.fallbackError );
				}
				context.status = 'sent';
				ref.reset();
			} catch ( error ) {
				context.status = 'error';
				context.error = error.message || context.fallbackError;
			}
		},
	},
	callbacks: {
		isSending: () => getContext().status === 'sending',
		isSent: () => getContext().status === 'sent',
		isError: () => getContext().status === 'error',
	},
} );
