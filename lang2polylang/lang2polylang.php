<?php
/**
 * Plugin Name: Lang2Polylang Linker
 * Description: Asocia posts en inglés con sus versiones en español (sufijo "-es"). Requiere Polylang.
 * Version: 2.0
 * Author: Div-IT
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function() {
	add_management_page( 'Lang2Polylang', 'Lang2Polylang', 'manage_options', 'lang2polylang', 'l2p_admin_page' );
} );

add_action( 'wp_ajax_l2p_get_count',     'l2p_ajax_get_count' );
add_action( 'wp_ajax_l2p_process_batch', 'l2p_ajax_process_batch' );

// ─── Dependency check ─────────────────────────────────────────────────────────

function l2p_check_polylang(): bool {
	return function_exists( 'pll_save_post_translations' );
}

// ─── Admin page ───────────────────────────────────────────────────────────────

function l2p_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	if ( ! l2p_check_polylang() ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>'
		   . 'El plugin <strong>Lang2Polylang</strong> requiere que <strong>Polylang</strong> esté instalado y activo.'
		   . '</p></div></div>';
		return;
	}

	$nonce = wp_create_nonce( 'l2p_ajax_nonce' );
	?>
	<div class="wrap">
		<h1>Lang2Polylang &mdash; Vincular Traducciones</h1>
		<p>Busca posts cuyo slug termine en <code>-es</code>, identifica su contraparte en inglés y los vincula en Polylang.</p>

		<?php
		$pll_langs    = pll_languages_list( [ 'fields' => 'slug' ] );
		$has_en       = in_array( 'en', $pll_langs, true );
		$has_es    = in_array( 'es', $pll_langs, true );
		$config_ready = $has_en && $has_es;
		$notice_type  = $config_ready ? 'success' : 'error';
		echo '<div class="notice notice-' . $notice_type . ' inline"><p>'
		   . '<strong>Idiomas en Polylang:</strong> ' . implode( ', ', array_map( fn($s) => "<code>$s</code>", $pll_langs ) )
		   . ( $has_en    ? '' : ' &mdash; falta <code>en</code>' )
		   . ( $has_es ? '' : ' &mdash; falta <code>es</code>' )
		   . ( $config_ready ? '' : '<br><strong>Configurá los idiomas en Polylang antes de continuar.</strong>' )
		   . '</p></div>';
		?>

		<div class="card" style="max-width:620px;padding:20px 24px;">
			<form id="l2p-form">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="l2p-post-types">Post types</label></th>
						<td>
							<input type="text" id="l2p-post-types" value="page" class="regular-text" placeholder="page,property,..." />
							<p class="description">Separados por coma. Por defecto: <code>page</code>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Modo</th>
						<td>
							<label>
								<input type="checkbox" id="l2p-dry-run" />
								<strong>Dry Run</strong> &mdash; previsualizar sin guardar cambios
							</label>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" class="button button-primary" id="l2p-start" <?php disabled( ! $config_ready ); ?>>Iniciar Vinculación</button>
					<span id="l2p-spinner" class="spinner" style="float:none;vertical-align:middle;visibility:hidden;margin-top:0;"></span>
					<?php if ( ! $config_ready ) : ?>
						<span style="color:#d63638;margin-left:10px;">Configuración de idiomas incompleta.</span>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<div id="l2p-progress-wrap" style="display:none;max-width:620px;margin-top:20px;">
			<progress id="l2p-progress" max="100" value="0" style="width:100%;height:22px;display:block;"></progress>
			<p id="l2p-progress-label" style="margin:6px 0 0;"></p>
		</div>

		<div id="l2p-results" style="margin-top:20px;max-width:720px;font-family:monospace;font-size:13px;"></div>
	</div>

	<script>
	(function ($) {
		'use strict';
		var AJAX_URL = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var NONCE    = <?php echo wp_json_encode( $nonce ); ?>;
		var BATCH    = 50;

		$('#l2p-form').on('submit', function (e) {
			e.preventDefault();

			var postTypes = $('#l2p-post-types').val().trim() || 'page';
			var dryRun    = $('#l2p-dry-run').is(':checked') ? 1 : 0;

			setUIBusy(true);
			$('#l2p-progress-wrap').hide();
			$('#l2p-results').html('');

			$.post(AJAX_URL, { action: 'l2p_get_count', nonce: NONCE, post_types: postTypes })
				.done(function (res) {
					if (!res.success) { showError(res.data || 'Error al obtener conteo.'); setUIBusy(false); return; }
					var total = res.data.total;
					if (total === 0) {
						$('#l2p-results').html('<div class="notice notice-warning inline"><p>No se encontraron candidatos con sufijo <code>-es</code>.</p></div>');
						setUIBusy(false);
						return;
					}
					if (dryRun) {
						appendNotice('info', '<strong>Dry Run activo</strong> &mdash; no se guardarán cambios.');
					}
					$('#l2p-progress').attr('max', total).val(0);
					$('#l2p-progress-wrap').show();
					processBatch(0, total, postTypes, dryRun, { linked: 0, skipped: 0, errors: 0 });
				})
				.fail(function () { showError('Error de red al obtener conteo.'); setUIBusy(false); });
		});

		function processBatch(offset, total, postTypes, dryRun, counters) {
			$.post(AJAX_URL, {
				action:     'l2p_process_batch',
				nonce:      NONCE,
				post_types: postTypes,
				dry_run:    dryRun,
				offset:     offset,
				limit:      BATCH
			})
			.done(function (res) {
				if (!res.success) { showError(res.data || 'Error en el procesamiento.'); setUIBusy(false); return; }
				var d = res.data;
				counters.linked  += d.linked;
				counters.skipped += d.skipped;
				counters.errors  += d.errors;

				$.each(d.items, function (i, item) {
					var icons  = { linked: '✅', dry_run: '👁️', skipped: '⚠️', error: '❌' };
					var line   = (icons[item.status] || '•') + '  ' + esc(item.slug_en) + ' ↔ ' + esc(item.slug_es);
					if (item.note) line += '  <span style="color:#999;">(' + esc(item.note) + ')</span>';
					$('#l2p-results').append('<div>' + line + '</div>');
				});

				var processed = Math.min(offset + BATCH, total);
				$('#l2p-progress').val(processed);
				$('#l2p-progress-label').text('Procesando ' + processed + ' de ' + total + '...');

				if (processed < total) {
					processBatch(processed, total, postTypes, dryRun, counters);
				} else {
					var label   = (dryRun ? 'Dry Run completado' : 'Proceso completado') + '.';
					var summary = ' <strong>' + counters.linked + ' vinculados</strong>'
					            + ' / ' + counters.skipped + ' saltados'
					            + ' / ' + counters.errors  + ' errores.';
					$('#l2p-progress-label').html(label + summary);
					setUIBusy(false);
				}
			})
			.fail(function () { showError('Error de red durante el procesamiento.'); setUIBusy(false); });
		}

		function setUIBusy(busy) {
			$('#l2p-start').prop('disabled', busy);
			$('#l2p-spinner').css('visibility', busy ? 'visible' : 'hidden');
		}

		function appendNotice(type, msg) {
			$('#l2p-results').append('<div class="notice notice-' + type + ' inline" style="margin:0 0 8px;"><p>' + msg + '</p></div>');
		}

		function showError(msg) {
			appendNotice('error', esc(msg));
		}

		function esc(str) {
			return $('<div>').text(String(str)).html();
		}

	}(jQuery));
	</script>
	<?php
}

// ─── Core: count candidates ───────────────────────────────────────────────────

function l2p_count_candidates( array $post_types ): int {
	global $wpdb;
	if ( empty( $post_types ) ) return 0;
	$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
	$params       = array_merge( [ '%-es' ], $post_types );
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_name LIKE %s AND post_type IN ($placeholders) AND post_status != 'trash'",
			...$params
		)
	);
}

// ─── Core: get candidates (paginated) ────────────────────────────────────────

function l2p_get_candidates( array $post_types, int $offset = 0, int $limit = 0 ): array {
	global $wpdb;
	if ( empty( $post_types ) ) return [];
	$placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
	$params       = array_merge( [ '%-es' ], $post_types );
	$limit_sql    = '';

	if ( $limit > 0 ) {
		$params[]  = $limit;
		$params[]  = $offset;
		$limit_sql = 'LIMIT %d OFFSET %d';
	}

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_name, post_type FROM {$wpdb->posts}
			 WHERE post_name LIKE %s AND post_type IN ($placeholders) AND post_status != 'trash'
			 ORDER BY ID ASC $limit_sql",
			...$params
		)
	);
}

// ─── Core: process batch ──────────────────────────────────────────────────────

function l2p_process_batch( bool $dry_run, array $post_types, int $offset, int $limit ): array {
	$candidates = l2p_get_candidates( $post_types, $offset, $limit );
	$result     = [ 'linked' => 0, 'skipped' => 0, 'errors' => 0, 'items' => [] ];

	foreach ( $candidates as $post_es ) {
		$slug_es = $post_es->post_name;
		$slug_en = preg_replace( '/-es$/', '', $slug_es );

		if ( $slug_en === $slug_es ) {
			$result['skipped']++;
			$result['items'][] = [ 'slug_es' => $slug_es, 'slug_en' => $slug_es, 'status' => 'skipped', 'note' => 'slug sin sufijo -es al final' ];
			continue;
		}

		$en_posts = get_posts( [
			'name'        => $slug_en,
			'post_type'   => $post_es->post_type,
			'numberposts' => 1,
			'post_status' => 'any',
		] );

		if ( empty( $en_posts ) ) {
			$result['skipped']++;
			$result['items'][] = [ 'slug_es' => $slug_es, 'slug_en' => $slug_en, 'status' => 'skipped', 'note' => 'contraparte EN no encontrada' ];
			continue;
		}

		$en_id = (int) $en_posts[0]->ID;
		$es_id = (int) $post_es->ID;

		if ( $dry_run ) {
			$result['linked']++;
			$result['items'][] = [ 'slug_es' => $slug_es, 'slug_en' => $slug_en, 'status' => 'dry_run', 'note' => "EN=$en_id ES=$es_id" ];
			continue;
		}

		pll_set_post_language( $en_id, 'en' );
		pll_set_post_language( $es_id, 'es' );
		pll_save_post_translations( [ 'en' => $en_id, 'es' => $es_id ] );

		// Verificar que Polylang realmente guardó el vínculo
		$saved = pll_get_post_translations( $en_id );
		if ( ! isset( $saved['es'] ) || (int) $saved['es'] !== $es_id ) {
			$available = implode( ', ', pll_languages_list() );
			$result['errors']++;
			$result['items'][] = [
				'slug_es' => $slug_es,
				'slug_en' => $slug_en,
				'status'  => 'error',
				'note'    => "Polylang no vinculó. Idiomas configurados: [$available]",
			];
			continue;
		}

		$result['linked']++;
		$result['items'][] = [ 'slug_es' => $slug_es, 'slug_en' => $slug_en, 'status' => 'linked', 'note' => '' ];
	}

	return $result;
}

// ─── AJAX: get count ──────────────────────────────────────────────────────────

function l2p_ajax_get_count() {
	check_ajax_referer( 'l2p_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

	$post_types = l2p_parse_post_types( wp_unslash( $_POST['post_types'] ?? 'page' ) );
	wp_send_json_success( [ 'total' => l2p_count_candidates( $post_types ) ] );
}

// ─── AJAX: process batch ──────────────────────────────────────────────────────

function l2p_ajax_process_batch() {
	check_ajax_referer( 'l2p_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );
	if ( ! l2p_check_polylang() )                 wp_send_json_error( 'Polylang no está activo.' );

	$pll_langs = pll_languages_list( [ 'fields' => 'slug' ] );
	if ( ! in_array( 'en', $pll_langs, true ) || ! in_array( 'es', $pll_langs, true ) ) {
		wp_send_json_error( 'Configuración incompleta: se requieren los idiomas "en" y "es" en Polylang. Idiomas actuales: ' . implode( ', ', $pll_langs ) );
	}

	$post_types = l2p_parse_post_types( wp_unslash( $_POST['post_types'] ?? 'page' ) );
	$dry_run    = ! empty( $_POST['dry_run'] );
	$offset     = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
	$limit      = min( 100, max( 1, (int) ( $_POST['limit'] ?? 50 ) ) );

	wp_send_json_success( l2p_process_batch( $dry_run, $post_types, $offset, $limit ) );
}

// ─── Helper ───────────────────────────────────────────────────────────────────

function l2p_parse_post_types( string $raw ): array {
	return array_values( array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) ) );
}
