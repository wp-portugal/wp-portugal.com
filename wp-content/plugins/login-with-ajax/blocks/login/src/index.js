import { registerBlockType, createBlock } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType('login-with-ajax/login', {
	edit: Edit,
	save() { return null; }, // we're rendering in PHP
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/legacy-widget' ],
				isMatch: ( { idBase, instance } ) => {
					if ( ! instance?.raw ) { return false; }
					return idBase === 'loginwithajaxwidget';
				},
				transform: ( { instance } ) => {
					return createBlock( 'login-with-ajax/login', {
						template : instance.raw.template,
						title : instance.raw.title,
						remember : instance.raw.remember,
						registration : instance.raw.registration,
						title_loggedin : instance.raw.title_loggedin,
						profile_link : instance.raw.profile_link,
						widget_title : true //legacy widget we know only gets generated in sidebars
					} );
				},
			},
		]
	},
});
