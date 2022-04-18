import { useBlockProps, InspectorControls, BlockControls } from '@wordpress/block-editor';
import {
	SelectControl,
	TextControl,
	RangeControl,
	ToggleControl,
	ColorPicker,
	CheckboxControl,
	ToolbarGroup, ToolbarButton,
	PanelBody,
	PanelRow, ToolbarItem
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

export default function Edit( { attributes, setAttributes } ) {
	let is_widget_area = typeof(wp.textWidgets) !== 'undefined'; //this works
	const [ hasFixedBackground, setHasFixedBackground ] = useState( false );
	return (
		<div { ...useBlockProps() }>
			<ServerSideRender block="login-with-ajax/login" attributes = {attributes} />
			<BlockControls>
				<ToolbarGroup label={__('Preview Mode','login-with-ajax')}>
					<ToolbarItem as="p" style={{'padding-left':'10px'}}>{__('Preview Mode','login-with-ajax')}</ToolbarItem>
					<ToolbarButton
						label={ attributes.v ? __('Viewing whilst logged out.','login-with-ajax'):__('Viewing whilst logged in.','login-with-ajax') }
						icon={ attributes.v ? ('unlock'):('lock') }
						className="lwa-view-toggle"
						onClick={( e ) => {
							e.currentTarget.blur();
							e.currentTarget.attributes.label = attributes.v ? __('Viewing logged in preview.','login-with-ajax'):__('Viewing logged out preview.','login-with-ajax');
							e.currentTarget.firstChild.classList.toggle("dashicons-edit");
							e.currentTarget.firstChild.classList.toggle("dashicons-smiley");
							setAttributes({ v: !attributes.v });
						}}
					/>
				</ToolbarGroup>
			</BlockControls>
			<InspectorControls key="inspector">
				<PanelBody title={ __( 'General Options', 'login-with-ajax' ) }>
					<PanelRow>
						<SelectControl
							label={ __( 'Template', 'login-with-ajax' ) }
							value={ attributes.template }
							options={LoginWithAjax.templates}
							onChange={ ( v ) => setAttributes( { template : v } ) }
						/>
					</PanelRow>
					<PanelRow>
						<ColorPicker
							label={ __( 'Template Color', 'login-with-ajax' ) }
							color={attributes.template_color.hex}
							defaultValue={attributes.template_color.hex}
							copyFormat="hsl"
							onChangeComplete={ ( v ) => {setAttributes( { template_color : { 'H':v.hsl.h, 'S':v.hsl.s, 'L':v.hsl.l, 'hex':v.hex} } ) } }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={ __( 'Guest Preview Mode','login-with-ajax' ) }
							help={ __('View login form as a guest when in editing/preview mode.', 'login-with-ajax') }
							checked={ attributes.v }
							onChange={ () => {
								setHasFixedBackground( ( state ) => ! state );
								setAttributes( { v : hasFixedBackground } );
							} }
						/>
					</PanelRow>
					{ is_widget_area &&
						<PanelRow>
							<CheckboxControl
								label={ __( 'Leagacy Widget Title', 'login-with-ajax' ) }
								value={ attributes.widget_title }
								checked={ attributes.widget_title }
								onChange={ ( v ) => setAttributes( { widget_title : v } ) }
								help={ __( 'New blocks do not display sidebar titles the same way legacy widgets do. If you would like to display the logged in/out titles the same way traditional widgets do for this block, check this box.', 'login-with-ajax' ) }
							/>
						</PanelRow>
					}
				</PanelBody>
				<PanelBody title={ __( 'Logged Out', 'login-with-ajax' ) }>
					<PanelRow>
						<TextControl
							label={ __( 'Title', 'login-with-ajax' )}
							value={ attributes.title }
							onChange={ ( v ) => setAttributes( { title : v } ) }
						/>
					</PanelRow>
					<PanelRow>
						<SelectControl
							label={__('Show Recover Password?', 'login-with-ajax')}
							value={ attributes.remember }
							onChange={ ( v ) => setAttributes( { remember : v } ) }
							options={ [
								{ value: 0, label: __('No Link', 'login-with-ajax') },
								{ value: 1, label: __('Show link with AJAX form', 'login-with-ajax') },
								{ value: 2, label: __('Show direct link', 'login-with-ajax') },
							] }
						/>
					</PanelRow>
					<PanelRow>
						<SelectControl
							label={__('AJAX Registration?', 'login-with-ajax')}
							value={ attributes.registration }
							onChange={ ( v ) => setAttributes( { registration : v } ) }
							options={ [
								{ value: 0, label: __('No Link', 'login-with-ajax') },
								{ value: 1, label: __('Show link with AJAX form', 'login-with-ajax') },
								{ value: 2, label: __('Show direct link', 'login-with-ajax') },
							] }
						/>
					</PanelRow>
				</PanelBody>
				<PanelBody title={ __( 'Logged In', 'login-with-ajax' ) }>
					<PanelRow>
						<TextControl
							label= {__( 'Title', 'login-with-ajax' ) }
							value={ attributes.title_loggedin }
							onChange={ ( v ) => setAttributes( { title_loggedin : v } ) }
						/>
					</PanelRow>
					<PanelRow>
						<CheckboxControl
							label={__('Show profile link?', 'login-with-ajax')}
							value={ attributes.profile_link }
							checked={ attributes.profile_link }
							onChange={ ( v ) => setAttributes( { profile_link : v } ) }
						/>
					</PanelRow>
					<PanelRow>
						<CheckboxControl
							label={__('Show round avatar picture?', 'login-with-ajax')}
							value={ attributes.avatar_rounded }
							checked={ attributes.avatar_rounded }
							onChange={ ( v ) => setAttributes( { avatar_rounded : v } ) }
						/>
					</PanelRow>
					<PanelRow>
						<RangeControl
							label={__('Avatar size (pixels)', 'login-with-ajax')}
							value={ attributes.avatar_size }
							onChange={ ( v ) => setAttributes( { avatar_size : v } ) }
							min={10}
							max={300}
						/>
					</PanelRow>
					<PanelRow>
						<CheckboxControl
							label={__('Display vertically?', 'login-with-ajax')}
							value={ attributes.loggedin_vertical }
							checked={ attributes.loggedin_vertical }
							onChange={ ( v ) => setAttributes( { loggedin_vertical : v } ) }
						/>
					</PanelRow>
				</PanelBody>
			</InspectorControls>
		</div>
	);
}
