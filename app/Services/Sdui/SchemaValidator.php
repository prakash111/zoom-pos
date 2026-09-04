<?php

namespace App\Services\Sdui;

/**
 * Validates the portable SDUI contract before a schema leaves Laravel.
 *
 * Database-authored screens and PHP-built settings screens pass through the
 * same validator, so unsupported primitives cannot fail silently in clients.
 */
class SchemaValidator
{
    /** @return list<string> */
    public function validate(array $schema): array
    {
        $errors = [];
        $layout = strtolower((string) ($schema['layout'] ?? 'scroll_view'));
        if (! in_array($layout, SchemaResponse::LAYOUT_TYPES, true)) {
            $errors[] = "layout: unsupported layout [{$layout}]";
        }

        if (! is_array($schema['components'] ?? null)) {
            $errors[] = 'components: must be an array';

            return $errors;
        }

        foreach ($schema['components'] as $index => $component) {
            $this->validateComponent($component, "components.{$index}", $errors);
        }

        if (isset($schema['fab']) && $schema['fab'] !== null) {
            $this->validateComponent($schema['fab'], 'fab', $errors);
        }

        foreach (($schema['app_bar']['actions'] ?? []) as $index => $actionButton) {
            if (! is_array($actionButton)) {
                $errors[] = "app_bar.actions.{$index}: must be an object";

                continue;
            }
            $this->validateAction($actionButton['action'] ?? null, "app_bar.actions.{$index}.action", $errors);
        }

        return $errors;
    }

    /** @param list<string> $errors */
    private function validateComponent(mixed $component, string $path, array &$errors): void
    {
        if (! is_array($component)) {
            $errors[] = "{$path}: must be an object";

            return;
        }

        $type = strtolower(trim((string) ($component['type'] ?? '')));
        if (! in_array($type, SchemaResponse::COMPONENT_TYPES, true)) {
            $errors[] = "{$path}.type: unsupported component [{$type}]";

            return;
        }

        if (in_array($type, SchemaResponse::INPUT_TYPES, true)
            && trim((string) ($component['name'] ?? '')) === '') {
            $errors[] = "{$path}.name: is required for input components";
        }

        if (in_array($type, SchemaResponse::ACTION_COMPONENT_TYPES, true)) {
            $this->validateAction($component['action'] ?? null, "{$path}.action", $errors);
        }

        if ($type === 'line_item_tile' && isset($component['action'])) {
            $this->validateAction($component['action'], "{$path}.action", $errors);
        }

        if ($type === 'action_sheet_trigger') {
            foreach (($component['options'] ?? []) as $index => $option) {
                $this->validateAction(
                    is_array($option) ? ($option['action'] ?? null) : null,
                    "{$path}.options.{$index}.action",
                    $errors
                );
            }
        }

        $children = $component['components'] ?? $component['children'] ?? $component['child'] ?? null;
        if (is_array($children)) {
            $children = array_is_list($children) ? $children : [$children];
            foreach ($children as $index => $child) {
                $this->validateComponent($child, "{$path}.components.{$index}", $errors);
            }
        }

        if ($type === 'tabs') {
            foreach (($component['tabs'] ?? []) as $tabIndex => $tab) {
                if (! is_array($tab)) {
                    $errors[] = "{$path}.tabs.{$tabIndex}: must be an object";

                    continue;
                }
                foreach (($tab['components'] ?? $tab['children'] ?? []) as $index => $child) {
                    $this->validateComponent($child, "{$path}.tabs.{$tabIndex}.components.{$index}", $errors);
                }
            }
        }
    }

    /** @param list<string> $errors */
    private function validateAction(mixed $action, string $path, array &$errors): void
    {
        if (! is_array($action)) {
            $errors[] = "{$path}: must be an object";

            return;
        }

        $type = strtolower(trim((string) ($action['type'] ?? '')));
        if (! in_array($type, SchemaResponse::ACTION_TYPES, true)) {
            $errors[] = "{$path}.type: unsupported action [{$type}]";

            return;
        }

        if (in_array($type, ['navigate', 'form_submit', 'api_post'], true)
            && trim((string) ($action['endpoint'] ?? $action['target_endpoint'] ?? '')) === '') {
            $errors[] = "{$path}.endpoint: is required for [{$type}]";
        }

        if (in_array($type, ['navigate', 'form_submit', 'api_post'], true)) {
            $endpoint = trim((string) ($action['endpoint'] ?? $action['target_endpoint'] ?? ''));
            if ($endpoint !== '' && ! str_starts_with($endpoint, '/api/')) {
                $errors[] = "{$path}.endpoint: must be a same-origin /api/ path";
            }
        }

        if ($type === 'form_submit') {
            $method = strtoupper((string) ($action['method'] ?? 'POST'));
            if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $errors[] = "{$path}.method: unsupported form method [{$method}]";
            }
        }

        if ($type === 'open_modal') {
            foreach (($action['components'] ?? []) as $index => $component) {
                $this->validateComponent($component, "{$path}.components.{$index}", $errors);
            }
        }
    }
}
