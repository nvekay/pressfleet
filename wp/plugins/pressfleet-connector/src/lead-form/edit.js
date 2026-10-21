import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextareaControl, ToggleControl } from '@wordpress/components';

/**
 * The form as visitors see it, with the button text editable in place and the rest in the sidebar.
 * The live form is rendered by render.php.
 *
 * @param {Object}                       props
 * @param {Object}                       props.attributes
 * @param {(attributes: Object) => void} props.setAttributes
 */
export default function Edit( { attributes, setAttributes } ) {
	const { buttonText, successMessage, showMessage } = attributes;
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form', 'pressfleet-connector' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Ask for a message', 'pressfleet-connector' ) }
						checked={ showMessage }
						onChange={ ( value ) => setAttributes( { showMessage: value } ) }
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Message after sending', 'pressfleet-connector' ) }
						value={ successMessage }
						onChange={ ( value ) => setAttributes( { successMessage: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps( { className: 'pressfleet-lead-form' } ) }>
				<input type="text" placeholder={ __( 'Your name', 'pressfleet-connector' ) } disabled />
				<input type="text" placeholder={ __( 'Email or phone', 'pressfleet-connector' ) } disabled />
				{ showMessage && (
					<textarea placeholder={ __( 'What would you like help with?', 'pressfleet-connector' ) } disabled />
				) }
				<div className="wp-block-button">
					<RichText
						tagName="span"
						className="wp-block-button__link wp-element-button"
						value={ buttonText }
						onChange={ ( value ) => setAttributes( { buttonText: value } ) }
						allowedFormats={ [] }
						withoutInteractiveFormatting
					/>
				</div>
			</div>
		</>
	);
}
