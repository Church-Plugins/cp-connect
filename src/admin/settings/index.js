import { createRoot, useRef, useState } from '@wordpress/element';
import './index.scss';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import FormControl from '@mui/material/FormControl';
import Select from '@mui/material/Select';
import InputLabel from '@mui/material/InputLabel';
import MenuItem from '@mui/material/MenuItem';
import Tabs from '@mui/material/Tabs';
import Tab from '@mui/material/Tab';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Skeleton from '@mui/material/Skeleton';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import platforms from './platforms';
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import optionsStore from './store';

import '@fontsource/roboto/300.css';
import '@fontsource/roboto/400.css';
import '@fontsource/roboto/500.css';
import '@fontsource/roboto/700.css';

const theme = createTheme({
	palette: {
		mode: "light"
	},
})

function DynamicTab({ tab }) {
	const { optionGroup, defaultData, component } = tab

	const { data, isSaving, error, isDirty, isHydrating } = useSelect((select) => {
		return {
			data: select(optionsStore).getOptionGroup(optionGroup),
			isSaving: select(optionsStore).isSaving(),
			error: select(optionsStore).getError(),
			isDirty: select(optionsStore).isDirty(),
			isHydrating: select(optionsStore).isResolving( 'getOptionGroup', [ optionGroup ] )
		}
	}, [optionGroup])

	const { persistOptionGroup, setOptionGroup } = useDispatch(optionsStore)

	const updateField = (field, value) => {
		setOptionGroup(optionGroup, {
			...data,
			[field]: value
		})
	}

	const save = () => {
		persistOptionGroup(optionGroup, data)
	}

	return (
		<Box>
			{
				isHydrating && 
				<>
				<Skeleton variant="text" width={500} />
				<Skeleton variant="text" width={200} />
				<Skeleton variant="text" width={250} />
				<Skeleton variant="text" width={300} height={40} />
				<Skeleton variant="text" width={300} height={40} />
				<Skeleton variant="text" width={300} height={40} />
				</>
			}
			{
				!isHydrating &&
				component({
					data: { ...defaultData, ...data },
					updateField,
					save,
					isSaving,
					error,
					isDirty,
					isHydrating,
				})
			}
			<Button
				sx={{ mt: 4 }}
				variant="contained"
				onClick={save}
				disabled={isSaving || !isDirty}
			>{ __( 'Save', 'cp-connect' ) }</Button>
		</Box>
		
	)
}

function TabPanel(props) {
  const { children, value, index, ...other } = props;

  return (
    <div
      role="tabpanel"
      hidden={value !== index}
      id={`simple-tabpanel-${index}`}
      aria-labelledby={`simple-tab-${index}`}
      {...other}
    >
      {value === index && (
        <Card sx={{ p: 4 }} variant="outlined">
          <Typography>{children}</Typography>
        </Card>
      )}
    </div>
  );
}

function Settings() {
	const [platform, setPlatform] = useState(Object.keys(platforms)[0])
	const platformData = platforms[platform]

	// creates a list of tabs based on the selected ChMS
	const tabsNames = [
		'select',
		...platformData.tabs.map((tab) => tab.optionGroup),
		'license'
	]

	const openTab = (index) => {
		const url = new URL(window.location.href)
		url.searchParams.set('tab', tabsNames[index])
		window.history.pushState({}, '', url)
		setCurrentTab(index)
	}

	const [currentTab, setCurrentTab] = useState(() => {
		const url = new URL(window.location.href)
		const tab = url.searchParams.get('tab')

		if (tab) {
			return tabsNames.indexOf(tab)
		}

		return 0
	})

	return (
		<ThemeProvider theme={theme}>
			<Box sx={{ height: '100%', p: 2 }}>
				<h1>CP Connect</h1>
				<Tabs value={currentTab} onChange={(_, value) => openTab(value)} sx={{ px: 2, mb: '-2px', mt: 4 }}>
					<Tab label={__( 'Select a ChMS', 'cp-connect' )} />
					{
						platformData.tabs.map((tab) => (
							<Tab key={tab.optionGroup} label={tab.name} />
						))
					}
					<Tab label={__( 'License', 'cp-connect' )} />
				</Tabs>
				<TabPanel value={currentTab} index={0}>
					<FormControl>
						<InputLabel id="chms-select-label">ChMS</InputLabel>
						<Select
							labelId="chms-select-label"
							label={__( 'ChMS', 'cp-connect' )}
							value={platform}
							onChange={(e) => setPlatform(e.target.value)}
						>
							{Object.keys(platforms).map((key) => (
								<MenuItem key={key} value={key}>{platforms[key].name}</MenuItem>
							))}
						</Select>
					</FormControl>
				</TabPanel>
				{
					platformData.tabs.map((tab, index) => (
						<TabPanel key={tab.optionGroup} value={currentTab} index={index + 1}>
							<DynamicTab tab={tab} />
						</TabPanel>
					))
				}
				<TabPanel value={currentTab} index={platformData.tabs.length + 2}>
					<h2>License</h2>
				</TabPanel>
			</Box>
		</ThemeProvider>
	)
}

document.addEventListener('DOMContentLoaded', function () {
	const root = document.querySelector('.cp_settings_root.cp-connect')

	if (root) {
		createRoot(root).render(
			<Settings />
		)
	}
})
