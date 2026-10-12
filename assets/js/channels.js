/**
 * Which Google Analytics 4 channel a link's tags land in, so people find out before sharing a
 * link that it will show up as "Unassigned".
 *
 * This follows GA4's default channel group rules, checked in GA4's order (the first match wins):
 * https://support.google.com/analytics/answer/9756891
 * GA4 also sorts by source, using a long list of sites (google is search, facebook is social,
 * and so on). Only well-known sites are listed here. A site missing from this list can only make
 * the check warn when GA4 wouldn't, never the other way round, which is the safe side to err on.
 * Matching ignores case, like GA4's.
 *
 * SunshineUTMChannels.channel( source, medium, campaign ) gives { name, unassigned, hint }.
 */
( function () {
	'use strict';

	var SEARCH = [ 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu', 'yandex', 'ecosia', 'ask', 'aol', 'naver', 'seznam', 'qwant', 'msn', 'daum', 'sogou', 'startpage', 'search.yahoo.com', 'search.aol.com', 'yandex.ru' ];
	var SOCIAL = [ 'facebook', 'fb', 'm.facebook.com', 'l.facebook.com', 'lm.facebook.com', 'instagram', 'ig', 'l.instagram.com', 'twitter', 't.co', 'linkedin', 'lnkd.in', 'pinterest', 'reddit', 'tiktok', 'snapchat', 'quora', 'tumblr', 'vk.com', 'whatsapp', 'messenger' ];
	var VIDEO = [ 'youtube', 'm.youtube.com', 'vimeo', 'twitch', 'dailymotion' ];
	var SHOPPING = [ 'amazon', 'ebay', 'etsy', 'walmart', 'igshopping' ];

	var PAID = /^(.*cp.*|ppc|retargeting|paid.*)$/;
	var SHOP_CAMPAIGN = /^(.*(([^a-df-z]|^)shop|shopping).*)$/;

	// "google" also matches "google.com" and "www.google.com", as GA4's list has both.
	function inList( source, list ) {
		var bare = source.replace( /^www\./, '' );
		return list.some( function ( site ) {
			return bare === site || bare === site + '.com';
		} );
	}

	function oneOf( value, list ) {
		return list.indexOf( value ) !== -1;
	}

	function name( source, medium, campaign ) {
		var paid = PAID.test( medium );
		var search = inList( source, SEARCH );
		var social = inList( source, SOCIAL );
		var video = inList( source, VIDEO );
		var shopping = inList( source, SHOPPING ) || SHOP_CAMPAIGN.test( campaign );
		var email = [ 'email', 'e-mail', 'e_mail', 'e mail' ];

		if ( campaign.indexOf( 'cross-network' ) !== -1 ) {
			return 'Cross-network';
		}
		if ( shopping && paid ) {
			return 'Paid Shopping';
		}
		if ( search && paid ) {
			return 'Paid Search';
		}
		if ( social && paid ) {
			return 'Paid Social';
		}
		if ( video && paid ) {
			return 'Paid Video';
		}
		if ( oneOf( medium, [ 'display', 'banner', 'expandable', 'interstitial', 'cpm' ] ) ) {
			return 'Display';
		}
		if ( paid ) {
			return 'Paid Other';
		}
		if ( shopping ) {
			return 'Organic Shopping';
		}
		if ( social || oneOf( medium, [ 'social', 'social-network', 'social-media', 'sm', 'social network', 'social media' ] ) ) {
			return 'Organic Social';
		}
		if ( video || /^(.*video.*)$/.test( medium ) ) {
			return 'Organic Video';
		}
		if ( search || medium === 'organic' ) {
			return 'Organic Search';
		}
		if ( oneOf( medium, [ 'referral', 'app', 'link' ] ) ) {
			return 'Referral';
		}
		if ( oneOf( source, email ) || oneOf( medium, email ) ) {
			return 'Email';
		}
		if ( medium === 'affiliate' ) {
			return 'Affiliates';
		}
		if ( medium === 'audio' ) {
			return 'Audio';
		}
		if ( source === 'sms' || medium === 'sms' ) {
			return 'SMS';
		}
		if ( /push$/.test( medium ) || medium.indexOf( 'mobile' ) !== -1 || medium.indexOf( 'notification' ) !== -1 || source === 'firebase' ) {
			return 'Mobile Push Notifications';
		}
		return '';
	}

	// A suggestion for a medium GA4 doesn't know, when it's clear what was meant.
	function hint( medium ) {
		var guesses = [
			[ /^(fb|facebook|insta|instagram|ig|twitter|x|linkedin|li|tiktok|pinterest|reddit|threads|bluesky|social.*|.*social)$/, 'social' ],
			[ /^(newsletter|mail|.*email.*|.*newsletter.*|mailchimp|klaviyo)$/, 'email' ],
			[ /^(paid.*|ads?|adwords|ppc.*|.*cpc.*)$/, 'cpc' ],
			[ /^(partner|partners|affiliates)$/, 'affiliate' ],
			[ /^(text|texts|txt)$/, 'sms' ],
			[ /^(banner.*|programmatic)$/, 'display' ],
			[ /^(podcast|radio|spotify)$/, 'audio' ],
			[ /^(website|web|blog|backlink|links?)$/, 'referral' ],
		];
		for ( var i = 0; i < guesses.length; i++ ) {
			if ( guesses[ i ][ 0 ].test( medium ) ) {
				return guesses[ i ][ 1 ];
			}
		}
		return '';
	}

	function channel( source, medium, campaign ) {
		source = String( source || '' ).toLowerCase().trim();
		medium = String( medium || '' ).toLowerCase().trim();
		campaign = String( campaign || '' ).toLowerCase().trim();
		var found = name( source, medium, campaign );
		return {
			name: found || 'Unassigned',
			unassigned: found === '',
			hint: found === '' ? hint( medium ) : '',
		};
	}

	window.SunshineUTMChannels = { channel: channel };
} )();
