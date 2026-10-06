/**
 * SITE PERSPECTIVES: Shortcode tab
 *
 * Live preview in an isolated iframe, theme and color pickers,
 * and AJAX saving.
 */

( function ( $ ) {
    'use strict';

    var config = window.sitePerspectivesShortcode || {};
    var dirty = false;
    var applyingTheme = false;
    var frame;
    var markupTimer = null;

    $( document ).ready( function () {
        frame = document.getElementById( 'siteperspectives-preview' );

        if ( ! frame || ! document.getElementById( 'siteperspectives-sc-form' ) ) {
            return;
        }

        initColors();
        buildPreview();
        bindControls();
        toggleProtectedNote();
    } );


    /**
     * Read every setting from the form.
     *
     * @return {Object}
     */
    function gather() {
        var form = $( '#siteperspectives-sc-form' );
        var settings = {
            theme: $( '#siteperspectives-theme' ).val(),
            colors: {},
            layout: $( '#siteperspectives-layout' ).val(),
            density: $( '#siteperspectives-density' ).val(),
            radius: $( '#siteperspectives-radius' ).val(),
            show_icons: form.find( '[name="show_icons"]' ).is( ':checked' ) ? 1 : 0,
            new_tab: form.find( '[name="new_tab"]' ).is( ':checked' ) ? 1 : 0,
            show_utility: form.find( '[name="show_utility"]' ).is( ':checked' ) ? 1 : 0,
            utility_heading: $( '#siteperspectives-utility-heading' ).val(),
            fields: [],
            meta: []
        };

        form.find( '.siteperspectives-color' ).each( function () {
            settings.colors[ $( this ).data( 'key' ) ] = $( this ).val();
        } );

        form.find( '[name="fields[]"]:checked' ).each( function () {
            settings.fields.push( this.value );
        } );

        form.find( '#siteperspectives-sc-meta .siteperspectives-meta-row' ).each( function () {
            var key = $.trim( $( this ).find( '.siteperspectives-meta-key' ).val() );

            if ( key ) {
                settings.meta.push( { key: key, label: $.trim( $( this ).find( '.siteperspectives-meta-label' ).val() ) } );
            }
        } );

        return settings;
    } // End gather()


    /**
     * Pick white text on dark and mid-tone backgrounds (relative luminance up to 0.22), black otherwise.
     *
     * @param {string} hex
     * @return {string}
     */
    function textColor( hex ) {
        var value = String( hex ).replace( '#', '' );

        if ( 3 === value.length ) {
            value = value.replace( /(.)/g, '$1$1' );
        }

        var luminance = 0;
        var weights = [ 0.2126, 0.7152, 0.0722 ];

        for ( var i = 0; i < 3; i++ ) {
            var channel = parseInt( value.substr( i * 2, 2 ), 16 ) / 255;

            luminance += weights[ i ] * ( channel <= 0.03928 ? channel / 12.92 : Math.pow( ( channel + 0.055 ) / 1.055, 2.4 ) );
        }

        return luminance <= 0.22 ? '#ffffff' : '#000000';
    } // End textColor()


    /**
     * Color pickers.
     */
    function initColors() {
        $( '.siteperspectives-color' ).wpColorPicker( {
            change: function ( event, ui ) {
                $( event.target ).val( ui.color.toString() );

                if ( ! applyingTheme ) {
                    $( '#siteperspectives-theme' ).val( 'custom' );
                }

                changed();
            },
            clear: function () {
                changed();
            }
        } );
    } // End initColors()


    /**
     * Build the preview document once, then update it in place.
     */
    function buildPreview() {
        var template = document.getElementById( 'siteperspectives-preview-template' );
        var markup = template ? template.innerHTML : '';

        if ( ! $.trim( markup ) ) {
            markup = '<p>' + config.i18n.noRows + '</p>';
        }

        frame.onload = applyPreview;
        frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' +
            '<link rel="stylesheet" href="' + config.iconsUrl + '">' +
            '<link rel="stylesheet" href="' + config.orgUrl + '">' +
            '<link rel="stylesheet" href="' + config.cssUrl + '">' +
            '<link rel="stylesheet" href="' + config.nodesUrl + '">' +
            '<link rel="stylesheet" href="' + config.previewUrl + '">' +
            '</head><body><nav id="tl-root" class="siteperspectives-sc" aria-label="Site map">' + markup + '</nav></body></html>';
    } // End buildPreview()


    /**
     * Apply the current settings to the preview document.
     */
    function applyPreview() {
        var doc = frame.contentDocument;
        var root = doc ? doc.getElementById( 'tl-root' ) : null;

        if ( ! root ) {
            return;
        }

        var settings = gather();
        var classes = [ 'siteperspectives-sc', 'siteperspectives-layout-' + settings.layout, 'siteperspectives-density-' + settings.density ];
        var vars = [];
        var allFields = [ 'path', 'post_type', 'categories', 'tags', 'terms', 'author', 'published', 'modified' ];

        $.each( allFields, function ( index, key ) {
            if ( $.inArray( key, settings.fields ) === -1 ) {
                classes.push( 'siteperspectives-hide-' + key );
            }
        } );

        if ( ! settings.show_icons ) {
            classes.push( 'siteperspectives-no-icons' );
        }

        $.each( settings.colors, function ( key, value ) {
            vars.push( '--siteperspectives-' + key.replace( /_/g, '-' ) + ':' + value );
        } );

        vars.push( '--siteperspectives-radius:' + settings.radius + 'px' );
        vars.push( '--tl-trunk-text:' + textColor( settings.colors.title || '#000000' ) );
        vars.push( '--tl-accent-text:' + textColor( settings.colors.accent || '#000000' ) );

        if ( 'frontend' === settings.theme ) {
            classes.push( 'siteperspectives-use-node-colors' );
        }

        root.className = classes.join( ' ' );
        root.setAttribute( 'style', vars.join( ';' ) );

        var utility = root.querySelector( '.siteperspectives-sc-utility' );

        if ( utility ) {
            utility.style.display = settings.show_utility ? '' : 'none';

            var heading = utility.querySelector( '.siteperspectives-sc-heading' );

            if ( heading ) {
                heading.textContent = settings.utility_heading;
                heading.style.display = settings.utility_heading ? '' : 'none';
            }
        }

        var flatUtility = root.querySelectorAll( '.siteperspectives-cards-list .siteperspectives-is-utility' );

        for ( var u = 0; u < flatUtility.length; u++ ) {
            flatUtility[ u ].style.display = settings.show_utility ? '' : 'none';
        }

        var links = root.querySelectorAll( 'a.siteperspectives-title' );

        for ( var i = 0; i < links.length; i++ ) {
            links[ i ].setAttribute( 'target', settings.new_tab ? '_blank' : '_self' );
        }

        var blocks = root.querySelectorAll( '.siteperspectives-data' );

        for ( var j = 0; j < blocks.length; j++ ) {
            var visible = false;
            var kids = blocks[ j ].children;

            for ( var k = 0; k < kids.length; k++ ) {
                if ( 'none' !== doc.defaultView.getComputedStyle( kids[ k ] ).display ) {
                    visible = true;
                    break;
                }
            }

            blocks[ j ].style.display = visible ? '' : 'none';
        }
    } // End applyPreview()


    /**
     * Rebuild the preview markup on the server (debounced), so new custom meta rows show their values.
     */
    function refreshMarkup() {
        clearTimeout( markupTimer );

        markupTimer = setTimeout( function () {
            $.post( config.ajaxUrl, { action: 'siteperspectives_shortcode_preview', nonce: config.nonce, settings: gather() }, function ( response ) {
                var doc = frame.contentDocument;
                var root = doc ? doc.getElementById( 'tl-root' ) : null;

                if ( response && response.success && root ) {
                    root.innerHTML = response.data.html;
                    applyPreview();
                }
            } );
        }, 500 );
    } // End refreshMarkup()


    /**
     * Show a warning when a meta key is protected (starts with an underscore).
     */
    function toggleProtectedNote() {
        var protectedKey = false;

        $( '#siteperspectives-sc-meta .siteperspectives-meta-key' ).each( function () {
            if ( 0 === $.trim( $( this ).val() ).indexOf( '_' ) ) {
                protectedKey = true;
            }
        } );

        $( '#siteperspectives-sc-meta-note' ).prop( 'hidden', ! protectedKey );
    } // End toggleProtectedNote()


    /**
     * Mark unsaved changes and refresh the preview.
     */
    function changed() {
        dirty = true;
        $( '#siteperspectives-save-reminder' ).fadeIn( 150 );
        applyPreview();
    } // End changed()


    /**
     * Form events.
     */
    function bindControls() {
        $( '#siteperspectives-theme' ).on( 'change', function () {
            var theme = $( this ).val();

            if ( config.themes[ theme ] ) {
                applyingTheme = true;

                $.each( config.themes[ theme ], function ( key, value ) {
                    $( '#siteperspectives-color-' + key ).wpColorPicker( 'color', value );
                } );

                applyingTheme = false;
            }

            changed();
        } );

        $( '#siteperspectives-sc-form' ).on( 'input change', 'select:not(#siteperspectives-theme), input[type="checkbox"], input[type="range"], #siteperspectives-utility-heading, .siteperspectives-meta-key, .siteperspectives-meta-label', function () {
            if ( 'siteperspectives-radius' === this.id ) {
                $( '#siteperspectives-radius-out' ).text( $( this ).val() + 'px' );
            }

            if ( $( this ).is( '.siteperspectives-meta-key, .siteperspectives-meta-label' ) ) {
                toggleProtectedNote();
                refreshMarkup();
            }

            changed();
        } );

        $( '#siteperspectives-sc-add-meta' ).on( 'click', function () {
            $( '#siteperspectives-sc-meta' ).append(
                '<div class="siteperspectives-meta-row">' +
                '<input type="text" class="siteperspectives-meta-key" placeholder="meta_key">' +
                '<input type="text" class="siteperspectives-meta-label" placeholder="Label (blank for none)">' +
                '<button type="button" class="siteperspectives-meta-remove" aria-label="Remove"><span class="dashicons dashicons-no-alt"></span></button>' +
                '</div>'
            );
            changed();
        } );

        $( '#siteperspectives-sc-meta' ).on( 'click', '.siteperspectives-meta-remove', function () {
            $( this ).closest( '.siteperspectives-meta-row' ).remove();
            toggleProtectedNote();
            refreshMarkup();
            changed();
        } );

        $( '.siteperspectives-preview-header .siteperspectives-orientation button' ).on( 'click', function () {
            $( this ).addClass( 'is-active' ).siblings().removeClass( 'is-active' );
            frame.style.width = $( this ).data( 'width' );
        } );

        $( '#siteperspectives-copy' ).on( 'click', function () {
            var button = $( this );
            var text = $( '#siteperspectives-sc-code' ).text();
            var done = function () {
                var original = button.text();

                button.text( config.i18n.copied );
                setTimeout( function () {
                    button.text( original );
                }, 1500 );
            };

            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText( text ).then( done );
            } else {
                var field = $( '<textarea>' ).val( text ).appendTo( 'body' );

                field[ 0 ].select();
                document.execCommand( 'copy' );
                field.remove();
                done();
            }
        } );

        $( '#siteperspectives-save' ).on( 'click', save );

        $( document ).on( 'keydown', function ( event ) {
            if ( ( event.ctrlKey || event.metaKey ) && 's' === event.key.toLowerCase() ) {
                event.preventDefault();
                save();
            }
        } );

        $( window ).on( 'beforeunload', function () {
            if ( dirty ) {
                return true;
            }
        } );
    } // End bindControls()


    /**
     * Save over AJAX.
     */
    function save() {
        var button = $( '#siteperspectives-save' );
        var label = button.html();

        if ( button.prop( 'disabled' ) ) {
            return;
        }

        button.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + config.i18n.saving + '...' );

        $.ajax( {
            url: config.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: { action: 'siteperspectives_save_shortcode', nonce: config.nonce, settings: gather() }
        } ).done( function ( response ) {
            if ( response.success ) {
                dirty = false;
                $( '#siteperspectives-save-reminder' ).hide();
                notify( config.i18n.saved, true );
            } else {
                notify( response.data || config.i18n.error, false );
            }
        } ).fail( function () {
            notify( config.i18n.error, false );
        } ).always( function () {
            button.prop( 'disabled', false ).html( label );
        } );
    } // End save()


    /**
     * Show a short status message after the Save button.
     *
     * @param {string} message
     * @param {boolean} success
     */
    function notify( message, success ) {
        $( '#siteperspectives-save-note' ).remove();

        var note = $( '<span id="siteperspectives-save-note"></span>' ).text( message ).addClass( success ? 'is-success' : 'is-error' );

        $( '#siteperspectives-save' ).after( note );

        setTimeout( function () {
            note.fadeOut( 300, function () {
                $( this ).remove();
            } );
        }, 3500 );
    } // End notify()

} )( jQuery );
