<?php
/**
 * Development-only REST write helper for importers, and the orphan cleanup that
 * makes the two-write path safe.
 *
 * WordPress's `register_rest_field` update callback runs *after* the post row is
 * inserted. VX attaches its typed `vx` values through that callback, so a `vx`
 * payload VX rejects leaves the post behind with none of its field values — a
 * record that looks imported and is not. Every importer that creates through
 * `/wp/v2/{type}` hits this; `vx_create_with_vx()` closes it by deleting the
 * post it just created when the `vx` write fails. See `spike/v1-two-write-trap.php`
 * for the raw evidence and `tools/verify-vx-model.php` for the automated check.
 *
 * A `tools/` importer that writes through the abilities API
 * (`wp_get_ability('vx-framework/update-post')->execute(...)`, which is how
 * `tools/seed.php` seeds) does not hit this: values go in with the same call
 * that writes the record. This is for a client that replays content over
 * `/wp/v2`, where the two writes are separate. Require it the same way:
 *
 *   require __DIR__ . '/vx-write-lib.php';
 *
 * It needs a booted WordPress with the REST server available; it does not
 * bootstrap the site itself.
 */

declare(strict_types=1);

if (! function_exists('vx_create_with_vx')) {
	/**
	 * Create a REST entity and write its `vx` values, deleting the post when the
	 * value write fails.
	 *
	 * Two calls, not one: core owns creation, VX owns the values. When the second
	 * call fails, the first one's post is removed before the caller sees the
	 * failure, so a stale field key, a rejected value, or a validation error
	 * never leaves a half-written record behind.
	 *
	 * @param string               $post_type REST base, e.g. `sv_scene`.
	 * @param array<string, mixed> $core      Core REST params (`title`, `status`, ...).
	 * @param array<string, mixed> $vx        Typed `vx` payload, keyed by field key.
	 * @param bool                 $strict    Send `vx_strict=1` so VX warnings block
	 *                                        the write instead of being reported and
	 *                                        dropped.
	 * @return array{ok: bool, id: int, status: int, code: string, errors: array<int, mixed>, data: mixed, orphan_deleted: bool}
	 *         `id` is 0 when creation itself failed. `orphan_deleted` records
	 *         whether the post created by a failed value write was removed.
	 */
	function vx_create_with_vx(string $post_type, array $core = [], array $vx = [], bool $strict = true): array
	{
		$result = vx_dispatch('POST', '/wp/v2/' . $post_type, $core);

		if (! $result['ok']) {
			return $result;
		}

		$result['id'] = (int) ($result['data']['id'] ?? 0);

		if ($vx === [] || $result['id'] === 0) {
			return $result;
		}

		$params = ['vx' => $vx];
		if ($strict) {
			$params['vx_strict'] = 1;
		}

		$write = vx_dispatch('POST', '/wp/v2/' . $post_type . '/' . $result['id'], $params);

		if ($write['ok']) {
			$result['status'] = $write['status'];
			$result['data']   = $write['data'];
			return $result;
		}

		// The value write failed, so the post from the first call is an orphan:
		// it holds none of the values the caller asked for. Remove it and report
		// the failure, not a half-written record.
		$result['ok']             = false;
		$result['status']         = $write['status'];
		$result['code']           = $write['code'];
		$result['errors']         = $write['errors'];
		$result['data']           = $write['data'];
		$result['orphan_deleted'] = vx_delete_orphan($result['id']);

		return $result;
	}

	/**
	 * Delete a post a failed import step created.
	 *
	 * Force-deletes: a trashed post is still an orphan, and it still holds the
	 * slug the import wants. A no-op when the post is already gone, so a caller
	 * can clean up in a `finally` without guarding first.
	 */
	function vx_delete_orphan(int $post_id): bool
	{
		if ($post_id <= 0 || get_post($post_id) === null) {
			return true;
		}

		return (bool) wp_delete_post($post_id, true);
	}

	/**
	 * One REST call, normalized. Never throws: a transport-level failure comes
	 * back as `ok => false` with status 0.
	 *
	 * @param array<string, mixed> $params
	 * @return array{ok: bool, id: int, status: int, code: string, errors: array<int, mixed>, data: mixed, orphan_deleted: bool}
	 */
	function vx_dispatch(string $method, string $route, array $params = []): array
	{
		$request = new WP_REST_Request($method, $route);
		$request->set_body_params($params);

		$response = rest_get_server()->dispatch($request);
		$status   = $response->get_status();
		$data     = $response->get_data();

		return [
			'ok'             => $status >= 200 && $status < 300,
			'id'             => 0,
			'status'         => $status,
			'code'           => is_array($data) && isset($data['code']) ? (string) $data['code'] : '',
			'errors'         => is_array($data) && isset($data['errors']) && is_array($data['errors']) ? $data['errors'] : [],
			'data'           => $data,
			'orphan_deleted' => false,
		];
	}
}
