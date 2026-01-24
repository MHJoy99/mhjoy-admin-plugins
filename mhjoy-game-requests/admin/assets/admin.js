/**
 * MHJoy Game Requests - Admin JavaScript
 */

jQuery(document).ready(function ($) {

    // Fulfill game button
    $('.fulfill-game').on('click', function (e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        const button = $(this);

        if (!confirm('Mark this game as fulfilled?')) {
            return;
        }

        button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: mhjoyGR.rest_url + 'admin/games/fulfill',
            method: 'POST',
            headers: {
                'X-WP-Nonce': mhjoyGR.nonce
            },
            data: JSON.stringify({ game_id: gameId }),
            contentType: 'application/json',
            success: function (response) {
                alert('✅ Game marked as fulfilled!');
                location.reload();
            },
            error: function (xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to fulfill game'));
                button.prop('disabled', false).text('✅ Fulfill');
            }
        });
    });

    // Delete game button
    $('.delete-game').on('click', function (e) {
        e.preventDefault();
        const gameId = $(this).data('game-id');
        const button = $(this);

        if (!confirm('Are you sure you want to delete this game request? This cannot be undone.')) {
            return;
        }

        button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: mhjoyGR.rest_url + 'admin/games/bulk-action',
            method: 'POST',
            headers: {
                'X-WP-Nonce': mhjoyGR.nonce
            },
            data: JSON.stringify({
                action: 'delete',
                game_ids: [gameId]
            }),
            contentType: 'application/json',
            success: function (response) {
                alert('🗑️ Game deleted successfully!');
                location.reload();
            },
            error: function (xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Failed to delete game'));
                button.prop('disabled', false).text('🗑️ Delete');
            }
        });
    });

    // Select all checkbox
    $('#select-all').on('change', function () {
        $('.game-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Bulk actions
    $('#doaction').on('click', function (e) {
        e.preventDefault();
        const action = $('#bulk-action-selector').val();
        const selectedGames = $('.game-checkbox:checked').map(function () {
            return $(this).val();
        }).get();

        if (!action) {
            alert('Please select a bulk action');
            return;
        }

        if (selectedGames.length === 0) {
            alert('Please select at least one game');
            return;
        }

        const confirmMsg = action === 'delete'
            ? `Delete ${selectedGames.length} game(s)? This cannot be undone.`
            : `Mark ${selectedGames.length} game(s) as fulfilled?`;

        if (!confirm(confirmMsg)) {
            return;
        }

        $(this).prop('disabled', true).text('Processing...');

        $.ajax({
            url: mhjoyGR.rest_url + 'admin/games/bulk-action',
            method: 'POST',
            headers: {
                'X-WP-Nonce': mhjoyGR.nonce
            },
            data: JSON.stringify({
                action: action,
                game_ids: selectedGames
            }),
            contentType: 'application/json',
            success: function (response) {
                alert('✅ Bulk action completed!');
                location.reload();
            },
            error: function (xhr) {
                alert('Error: ' + (xhr.responseJSON?.message || 'Bulk action failed'));
                $('#doaction').prop('disabled', false).text('Apply');
            }
        });
    });

    // Copy license code to clipboard
    $('body').on('click', 'code', function () {
        const code = $(this).text();
        navigator.clipboard.writeText(code).then(function () {
            alert('📋 Code copied to clipboard: ' + code);
        });
    });
});
