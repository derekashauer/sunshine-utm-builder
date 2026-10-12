/**
 * The "UTM link" panel in the block editor's post sidebar. Plain JavaScript (no build step), so it
 * uses wp.element.createElement instead of JSX.
 *
 * The button reads the post's address live from the editor, so it's right as soon as the post is
 * published, without a reload. The short links are the ones that pointed here when the editor
 * opened (Sunshine_UTM_Admin::editor_assets()).
 *
 * WordPress 6.6 moved PluginDocumentSettingPanel from wp.editPost to wp.editor, so both are tried.
 */
( function ( wp ) {
	'use strict';

	var data = window.SunshineUTMEditor || {};
	var texts = data.texts || {};
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! Panel || ! wp.plugins ) {
		return;
	}
	var el = wp.element.createElement;

	function UTMPanel() {
		var post = wp.data.useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				link: editor.getPermalink(),
				published: editor.isCurrentPostPublished(),
			};
		}, [] );

		var children = [];
		if ( post.published && post.link ) {
			children.push(
				el(
					wp.components.Button,
					{
						variant: 'secondary',
						href: data.builder + '&url=' + encodeURIComponent( post.link ),
						target: '_blank',
						rel: 'noopener',
						key: 'build',
					},
					texts.build,
					el( 'span', { className: 'screen-reader-text' }, ' ' + texts.new_tab )
				)
			);
		} else {
			children.push( el( 'p', { key: 'unpublished' }, texts.unpublished ) );
		}

		if ( data.links && data.links.length ) {
			children.push( el( 'p', { key: 'heading', style: { margin: '16px 0 4px', fontWeight: 600 } }, texts.short_links ) );
			children.push(
				el(
					'ul',
					{ key: 'links', style: { margin: 0 } },
					data.links.map( function ( link ) {
						return el(
							'li',
							{ key: link.short, style: { marginBottom: '4px' } },
							el( 'a', { href: link.details, target: '_blank', rel: 'noopener', style: { wordBreak: 'break-all' } }, link.short.replace( /^https?:\/\//, '' ) ),
							' ',
							el( 'span', { style: { color: '#757575' } }, link.clicks === 1 ? texts.click : texts.clicks.replace( '%s', link.clicks.toLocaleString() ) )
						);
					} )
				)
			);
		}

		return el( Panel, { name: 'sunshine-utm', title: texts.title, className: 'sunshine-utm-editor-panel' }, children );
	}

	wp.plugins.registerPlugin( 'sunshine-utm', { render: UTMPanel, icon: 'admin-links' } );
} )( window.wp );
