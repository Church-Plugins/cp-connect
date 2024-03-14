import Alert from '@mui/material/Alert';
import Autocomplete from '@mui/material/Autocomplete';
import Chip from '@mui/material/Chip';
import TextField from '@mui/material/TextField';
import FormControl from '@mui/material/FormControl';
import Select from '@mui/material/Select';
import InputLabel from '@mui/material/InputLabel';
import MenuItem from '@mui/material/MenuItem';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import optionsStore from '../store';
import useApi from './useApi';
import apiFetch from '@wordpress/api-fetch';

const labels = {
	'chms_id':             __( 'Group ID', 'cp-connect' ),
	'post_title':          __( 'Group Name', 'cp-connect' ),
	'post_content':        __( 'Description', 'cp-connect' ),
	'leader':              __( 'Group Leader', 'cp-connect' ),
	'start_date':          __( 'Start Date', 'cp-connect' ),
	'end_date':            __( 'End Date', 'cp-connect' ),
	'thumbnail_url':       __( 'Image ID', 'cp-connect' ),
	'frequency':           __( 'Meeting Frequency', 'cp-connect' ),
	'city':					       __( 'City', 'cp-connect' ),
	'state_or_region':     __( 'State/Region', 'cp-connect' ),
	'postal_code':         __( 'Postal Code', 'cp-connect' ),
	'meeting_time':        __( 'Meeting Time', 'cp-connect' ),
	'meeting_day':         __( 'Meeting Day', 'cp-connect' ),
	'cp_location':         __( 'Group Campus', 'cp-connect' ),
	'group_category':      __( 'Group Focus', 'cp-connect' ),
	'group_type':          __( 'Group Type', 'cp-connect' ),
	'group_life_stage':    __( 'Group Life Stage', 'cp-connect' ),
	'kid_friendly':        __( 'Kid Friendly', 'cp-connect' ),
	'handicap_accessible': __( 'Accessible', 'cp-connect' ),
	'virtual':             __( 'Virtual', 'cp-connect' ),
}

function MPFields({ data, updateField }) {
	const [fieldError, setFieldError] = useState(null)
	const [inputValue, setInputValue] = useState('')
	const api = useApi()

	const { group_fields } = data

	const testCurrentFields = () => {
		if(!api) return
		setFieldError(null)
		api.getGroups({
			top: 1,
			select: group_fields.join(',')
		}).then(groups => {
			const fields = Object.keys(groups[0])

			updateField('valid_fields', fields)
		}).catch((e) => {
			setFieldError(e.message)
		})
	}

	return (
		<>
			<Typography variant="h5">{ __( 'Fields', 'cp-connect' ) }</Typography>
			<Autocomplete
				multiple
				value={group_fields}
				onChange={(_, value) => {
					updateField('group_fields', value.map(v => typeof v === 'string' ? v : v.value))
				}}
				inputValue={inputValue}
				onInputChange={(e, value) => {
					const options = value.split(',').map(v => v.trim()).filter(v => v.length)

					if(options.length > 1 || value.endsWith(',')) {
						updateField('group_fields', group_fields.concat(options))
						setInputValue('')
					} else {
						setInputValue(value)
					}
				}}
				options={[]}
				freeSolo
				selectOnFocus
				clearOnBlur
				handleHomeEndKeys
				renderTags={(value, getTagProps) => (
					value.map((option, index) => (
						<Chip
							label={typeof option === 'string' ? option : option.value}
							{...getTagProps({ index })}
						/>
					))
				)}
				getOptionLabel={(option) => (
					typeof option === 'string' ? option : option.label
				)}
				filterOptions={(options, params) => (params.inputValue.length ? [
					{
						value: params.inputValue,
						label: `Add "${params.inputValue}"`
					}
				] : [])}
				renderInput={(params) => (
					<TextField {...params} label={__( 'Group Fields', 'cp-connect' )} />
				)}
				sx={{ maxWidth: '1200px' }}
			/>
			{
				fieldError && <Alert severity="error">
					{__( 'There is an error with the fields you have selected.', 'cp-connect' )}
					<br />
					<br />
					<pre><code>{fieldError}</code></pre>
				</Alert>
			}
			<Button onClick={testCurrentFields} sx={{ alignSelf: 'start' }} variant="outlined">{ __( 'Update', 'cp-connect' ) }</Button>
		</>
	)
}

export default function ConfigureTab({ data, updateField }) {
	const api = useApi()
	const [newLabel, setNewLabel] = useState('')
	const [newValue, setNewValue] = useState('')
	const [addingCustomField, setAddingCustomField] = useState(false)
	const [importStarted, setImportStarted] = useState(false)
	const [importError, setImportError] = useState(null)
	const [importPending, setImportPending] = useState(false)

	const {
		group_field_mapping,
		custom_group_field_mapping,
		group_fields,
		custom_fields,
		valid_fields = []
	} = data

	const allFields = [...group_fields, ...custom_fields]

	const updateMappingField = (key, value) => {
		const newMapping = { ...group_field_mapping }
		newMapping[key] = value

		updateField('group_field_mapping', newMapping)
	}

	const createCustomMappingField = (name, value) => {
		const slug = name.toLowerCase().replace(/[\s\-]/g, '_').replace(/[^\w]/g, '')
		const newMapping = { ...custom_group_field_mapping }
		newMapping[slug] = { name, value }
		updateField('custom_group_field_mapping', newMapping)
	}

	const updateCustomMappingField = (key, { name, value }) => {
		const newMapping = { ...custom_group_field_mapping }
		newMapping[key] = { name, value }
		updateField('custom_group_field_mapping', newMapping)
	}

	const startImport = () => {
		setImportPending(true)
		apiFetch({
			path: '/cp-connect/v1/ministry-platform/groups/pull',
			method: 'POST',
			data: {}
		}).then((response) => {
			setImportStarted(true)
		}).catch((e) => {
			setImportError(e.message)
		}).finally(() => {
			setImportPending(false)
		})
	}

	if (!api) {
		return (
			<Box>
				{__( 'Authenticating with Ministry Platform...', 'cp-connect' )}
			</Box>
		)
	}

	if(api && !api.isAuthenticated()) {
		return (
			<Alert severity="warning">
				{__( 'You need to authenticate with Ministry Platform to test fields.', 'cp-connect' )}
			</Alert>
		)
	}

	return (
		<Box display="flex" flexDirection="column" gap={2}>
			
			{importStarted && <Alert severity="success">{__( 'Import started', 'cp-connect' )}</Alert>}

			{importError && <Alert severity="error">{__( 'Error when starting import: ', 'cp-connect' )}{importError}</Alert>}

			<Button disabled={importStarted || importPending} variant="contained" color="secondary" sx={{ alignSelf: 'start' }} onClick={startImport}>
				{importPending ? __( 'Starting Import' ) : importStarted ? __( 'Import Started' ) : __( 'Start import', 'cp-connect' )}
			</Button>

			<MPFields data={data} updateField={updateField} />

			<Typography variant="h5">{ __( 'Field mapping',	'cp-connect' ) }</Typography>
			{Object.entries(labels).map(([key, label]) => (
				<FormControl key={key}>
					<Autocomplete
						value={data.group_field_mapping[key] || ''}
						onChange={(e, option) => {
							updateMappingField(key, option?.value || '')
						}}
						sx={{ width: '300px' }}
						options={[
							{ label: '--Ignore--', value: '' },
							...valid_fields.map((field) => ({ label: field, value: field }))
						]}
						renderInput={(params) => <TextField {...params} label={label} variant="outlined" />}
						isOptionEqualToValue={(option, value) => option.value === value}
					/>
				</FormControl>
			))}

			<Typography variant="h5">{ __( 'Custom field mapping', 'cp-connect' ) }</Typography>
			{Object.entries(custom_group_field_mapping).map(([key, { name, value }]) => (
				<Box key={key} display="flex" gap={1} alignItems="center">
					<TextField
						label={__( 'Field Name', 'cp-connect' )}
						value={name}
						onChange={(e) => updateCustomMappingField(key, { name: e.target.value, value: value })}
						variant="outlined"
						sx={{ width: '300px' }}
					/>
					<Autocomplete
						value={value}
						onChange={(e, option) => updateCustomMappingField(key, { name, value: option?.value || '' })}
						sx={{ width: '300px' }}
						options={valid_fields.map((field) => ({ label: field, value: field }))}
						renderInput={(params) => <TextField {...params} label={__( 'Field', 'cp-connect' )} variant="outlined" />}
						isOptionEqualToValue={(option, value) => option.value === value}
					/>
				</Box>
			))}

			{addingCustomField && (
				<Box display="flex" gap={1} alignItems="center">
					<TextField
						label={__( 'Field Name', 'cp-connect' )}
						variant="outlined"
						value={newLabel}
						onChange={(e) => setNewLabel(e.target.value)}
					/>
					<FormControl>
						<Autocomplete
							value={newValue}
							onChange={(e, option) => setNewValue(option?.value || '')}
							sx={{ width: '300px' }}
							options={valid_fields.map((field) => ({ label: field, value: field }))}
							renderInput={(params) => <TextField {...params} label={__( 'Field', 'cp-connect' )} variant="outlined" />}
							isOptionEqualToValue={(option, value) => option.value === value}
						/>
					</FormControl>
					<Button
						onClick={() => {
							setAddingCustomField(false)
							createCustomMappingField(newLabel, newValue)
							setNewLabel('')
							setNewValue('')
						}}
					>
						{ __( 'Create', 'cp-connect' ) }
					</Button>
				</Box>
			)}

			<Button
				onClick={() => setAddingCustomField(!addingCustomField)}
				variant="outlined"
				sx={{ width: '200px' }}
			>
				{ __( 'Add Custom Field', 'cp-connect' ) }
			</Button>
		</Box>
	)
}
