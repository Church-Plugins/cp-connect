

jQuery($ => {

	$('.cp-connect-field-select').each(buildMultiSelect)

	function buildMultiSelect() {
		const optionsContainer = $(this).find('.cp-connect-field-select__options')
		const addFieldInput = $(this).find('.cp-connect-field-select__add-input')
		const addFieldButton = $(this).find('.cp-connect-field-select__add-button')
		const optionId = $(this).data('option-id')

		let options = Object.values( optionsContainer.data('options') || {} )

		updateList()

		addFieldButton.on('click', e => {
			const value = addFieldInput.val()
			if ( value ) {
				options.push(value)
				updateList()
				addFieldInput.val('')
			}
		})

		function updateList() {
			const listItemTemplate = `<li class="cp-connect-field-select-item">
				<input class="cp-connect-field-select-item-value" name="${optionId}[fields][{value}]" type="text" value="{value}" />
				<button class="cp-connect-field-select-item-remove button button-secondary"><i class="material-icons">delete</i></button>
			</li>`;

			optionsContainer.html('')

			options.forEach(value => {
				const elem = $(listItemTemplate.replaceAll('{value}', value))

				elem.find('.cp-connect-field-select-item-remove').on('click', e => {
					elem.remove()
					options = options.filter(v => v !== value)
				})

				optionsContainer.append(elem)
			})
		}
	}

	/**
	 * Bind event handlers to elements that are added to the DOM in real time
	 */
	let bindItems = () => {

		$( '.cp-connect-custom-mappings span.dashicons-dismiss' ).each(
			( index, element ) => {

				$( element ).off( 'click' ).on( 'click', ( event ) => {

					event.preventDefault();
					let target = $( element ).parents( '.cp-connect-field-mapping-item-container' )[0];
					$( target ).remove();

				} );
			}
		);

	};

	/**
	 * Add a new field mapping
	 *
	 * unbind and rebind to `click` event to prevent multiple calls
	 */
	$( '.cp-connect-add-field-mapping' )
		.off('click')
		.on( 'click',
			( event ) => {

				event.preventDefault();

				let rawFieldData = JSON.parse( $( 'input[name="ministry_platform_group_valid_fields"]' ).val() );

				let list = '<table class="form-table" role="presentation"><tbody><tr><td><select name="cp_connect_field_mapping_targets[]">';
				$( rawFieldData ).each(
					( index, value ) => {

						if( 'select' === value ) {
							list += '<option disabled> ' + value + ' </option>';
						} else {
							list += '<option> ' + value + ' </option>';
						}

					}
				);
				list += '</select><span class="dashicons dashicons-dismiss"></span></td></tr></tbody></table>';

				let newItem =
					'<div class="cp-connect-field-mapping-item-container">' +
						'<input type="text" name="cp_connect_field_mapping_names[]" value="" placeholder="Field Name" />' +
						list +
					'</div>';

				let target = $( '.cp-connect-custom-mappings .cp-connect-custom-mappings__last_row' );
				$( newItem ).insertBefore( $( target ) );

				setTimeout(
					() => {
						bindItems();
					}, 200
				);

			}
	);

	setTimeout(
		() => {
			bindItems();
		}, 200
	);
})
