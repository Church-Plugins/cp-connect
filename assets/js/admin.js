

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
})
