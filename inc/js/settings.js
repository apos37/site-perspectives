/**
 * SITE PERSPECTIVES: Settings
 *
 * AJAX save with Ctrl+S support and live preview of the menu title, page title, menu icon
 * and logo, matching Admin Help Docs' settings behavior. Also handles import and reset.
 */

( function ( $ ) {
    'use strict';

    var config = window.sitePerspectivesSettings || {};
    var saveButton;
    var originalText;
    var savingInterval;
    var isDirty = false;

    $( document ).ready( function () {
        saveButton = $( '#siteperspectives-save' );

        if ( ! saveButton.length ) {
            return;
        }

        originalText = saveButton.text();

        $( document ).on( 'input change', '#siteperspectives-settings-form [name]', markDirty );

        $( document ).on( 'input', '#siteperspectives_menu_title', function () {
            $( 'li#toplevel_page_site-perspectives .wp-menu-name' ).text( $( this ).val() || '...' );
        } );

        $( document ).on( 'input', '#siteperspectives_page_title', function () {
            $( '#siteperspectives-header h1' ).text( $( this ).val() || '...' );
        } );

        $( document ).on( 'change', '#siteperspectives_menu_icon', function () {
            $( 'li#toplevel_page_site-perspectives .wp-menu-image' ).attr( 'class', 'wp-menu-image dashicons-before dashicons-' + $( this ).val() );
        } );

        $( document ).on( 'input', '#siteperspectives_logo', function () {
            var url = $( this ).val();
            var target = $( '#siteperspectives-header .logo' );

            if ( url ) {
                target.attr( 'src', url ).show();
            } else {
                target.hide();
            }
        } );

        $( document ).on( 'change', '#siteperspectives-upload-settings', importSettings );

        $( document ).on( 'click', '#siteperspectives-reset-settings', resetSettings );

        saveButton.on( 'click', function ( event ) {
            event.preventDefault();
            saveSettings();
        } );

        $( document ).on( 'keydown', function ( event ) {
            if ( ( event.ctrlKey || event.metaKey ) && 's' === event.key.toLowerCase() ) {
                event.preventDefault();
                saveSettings();
            }
        } );
    } );


    /**
     * Show the save reminder.
     */
    function markDirty() {
        if ( isDirty ) {
            return;
        }

        isDirty = true;
        $( '#siteperspectives-save-reminder' ).fadeIn( 200 );
    } // End markDirty()


    /**
     * Collect every named field within the settings grid.
     *
     * @return {Object}
     */
    function gatherSettings() {
        var data = {};

        $( '#siteperspectives-settings-form [name]' ).each( function () {
            var field = $( this );
            var name = field.attr( 'name' );

            if ( 'checkbox' === field.attr( 'type' ) ) {
                if ( name.slice( -2 ) === '[]' ) {
                    var key = name.slice( 0, -2 );

                    data[ key ] = data[ key ] || [];

                    if ( field.is( ':checked' ) ) {
                        data[ key ].push( field.val() );
                    }
                } else {
                    data[ name ] = field.is( ':checked' ) ? 1 : 0;
                }

                return;
            }

            data[ name ] = field.val();
        } );

        return data;
    } // End gatherSettings()


    /**
     * Save all settings via AJAX.
     */
    function saveSettings() {
        if ( saveButton.prop( 'disabled' ) ) {
            return;
        }

        var originalTitle = startSavingTitle();

        $( '#siteperspectives-save-reminder' ).hide();
        isDirty = false;
        showSaving();

        $.ajax( {
            url: config.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: { action: 'siteperspectives_save_settings', nonce: config.nonce, settings: gatherSettings() }
        } ).done( function ( response ) {
            stopSavingTitle( originalTitle );

            if ( response.success ) {
                showResult( config.savedText, true );
            } else {
                showResult( response.data || config.errorText, false );
            }
        } ).fail( function () {
            stopSavingTitle( originalTitle );
            showResult( config.errorText, false );
        } );
    } // End saveSettings()


    /**
     * Read an exported file and send it to the server.
     *
     * @param {Event} event
     */
    function importSettings( event ) {
        var file = event.target.files[ 0 ];

        if ( ! file ) {
            return;
        }

        if ( ! window.confirm( config.confirmImport ) ) {
            $( event.target ).val( '' );
            return;
        }

        var reader = new FileReader();

        reader.onload = function ( loaded ) {
            $( '#siteperspectives-upload-filename' ).text( file.name ).show();

            $.ajax( {
                url: config.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: { action: 'siteperspectives_import', nonce: config.nonce, payload: loaded.target.result }
            } ).done( function ( response ) {
                if ( response.success ) {
                    showResult( config.importOk, true );
                    setTimeout( function () {
                        window.location.reload();
                    }, 900 );
                } else {
                    showResult( response.data || config.importError, false );
                }
            } ).fail( function ( xhr ) {
                var detail = xhr.responseText ? xhr.responseText.substring( 0, 200 ) : '';
                showResult( config.importError + ' (HTTP ' + xhr.status + ') ' + detail, false );
            } );
        };

        reader.readAsText( file );
    } // End importSettings()


    /**
     * Reset every option.
     */
    function resetSettings() {
        if ( ! window.confirm( config.confirmReset ) ) {
            return;
        }

        $.ajax( {
            url: config.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: { action: 'siteperspectives_reset', nonce: config.nonce }
        } ).done( function ( response ) {
            if ( response.success ) {
                showResult( config.resetOk, true );
                setTimeout( function () {
                    window.location.reload();
                }, 900 );
            } else {
                showResult( response.data || config.errorText, false );
            }
        } ).fail( function () {
            showResult( config.errorText, false );
        } );
    } // End resetSettings()


    /**
     * Animate the browser tab title while saving.
     *
     * @return {string}
     */
    function startSavingTitle() {
        var originalTitle = document.title;
        var dots = 0;

        document.title = config.savingText;

        savingInterval = setInterval( function () {
            dots = ( dots + 1 ) % 4;
            document.title = config.savingText + '.'.repeat( dots );
        }, 500 );

        return originalTitle;
    } // End startSavingTitle()


    /**
     * Restore the browser tab title.
     *
     * @param {string} originalTitle
     */
    function stopSavingTitle( originalTitle ) {
        clearInterval( savingInterval );
        document.title = originalTitle;
    } // End stopSavingTitle()


    /**
     * Show the spinning save state.
     */
    function showSaving() {
        saveButton.addClass( 'siteperspectives-button-saving' ).prop( 'disabled', true ).html( '<span class="dashicons dashicons-update spin"></span> ' + config.savingText + '...' );
        $( '#siteperspectives-save-note' ).remove();
    } // End showSaving()


    /**
     * Show the result message next to the save button.
     *
     * @param {string} message
     * @param {boolean} success
     */
    function showResult( message, success ) {
        saveButton.removeClass( 'siteperspectives-button-saving' ).prop( 'disabled', false ).text( originalText );

        var note = $( '<span id="siteperspectives-save-note"></span>' ).text( message ).addClass( success ? 'is-success' : 'is-error' );

        saveButton.after( note );

        setTimeout( function () {
            note.fadeOut( 400, function () {
                $( this ).remove();
            } );
        }, 4000 );
    } // End showResult()

} )( jQuery );
