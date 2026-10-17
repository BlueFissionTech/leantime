<?php

namespace Leantime\Domain\Tickets\Support;

/**
 * Pure boundary for a trusted policy/render adapter, NOT a production policy adapter.
 * No adapter is installed: absent or incomplete policy fails closed. Coverage is a
 * required adapter assertion, not evidence of deployed policy qualification.
 */
final class ConditionalNotificationPlan
{
    public const COVERAGE = ['project_membership', 'project_relevance', 'event_preferences',
        'author_exclusion', 'mention_bypass', 'plugin_filters', 'messenger', 'in_app'];

    public static function prepare(array $intent, ?array $policy = null): array
    {
        if ($policy === null) {
            return ['state' => 'blocked', 'reason' => 'policy_adapter_unqualified'];
        }
        if (($intent['contract'] ?? null) !== 'ticket.text_patched.v2'
            || ! is_string($intent['operationKey'] ?? null)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $intent['operationKey'])
            || ! isset($intent['before'], $intent['after'], $intent['version'])) {
            return ['state' => 'blocked', 'reason' => 'unsupported_intent'];
        }
        foreach (['actorId', 'projectId', 'ticketId'] as $identity) {
            if (! is_int($intent[$identity] ?? null) || $intent[$identity] < 1) {
                return ['state' => 'blocked', 'reason' => 'unsupported_intent'];
            }
        }
        foreach (['version', 'previousVersion'] as $version) {
            if (! is_string($intent[$version] ?? null) || ! preg_match('/\Aticket-revision-v1:[1-9][0-9]{0,18}\z/', $intent[$version])) {
                return ['state' => 'blocked', 'reason' => 'unsupported_intent'];
            }
        }
        foreach (['before', 'after'] as $image) {
            if (! is_array($intent[$image])) {
                return ['state' => 'blocked', 'reason' => 'unsupported_intent'];
            }
            $keys = array_keys($intent[$image]);
            sort($keys);
            if ($keys !== ['acceptanceCriteria', 'description', 'headline']) {
                return ['state' => 'blocked', 'reason' => 'unsupported_intent'];
            }
            foreach ($intent[$image] as $value) {
                if ($value !== null && (! is_string($value) || preg_match('//u', $value) !== 1)) {
                    return ['state' => 'blocked', 'reason' => 'unsupported_intent'];
                }
            }
        }
        $digest = hash('sha256', json_encode($intent, JSON_THROW_ON_ERROR));
        if (($policy['contract'] ?? null) !== 'ticket-notification-policy-v1'
            || ($policy['intentDigest'] ?? null) !== $digest
            || ! is_string($policy['revision'] ?? null) || $policy['revision'] === ''
            || ! is_array($policy['deliveries'] ?? null) || ! array_is_list($policy['deliveries'])) {
            return ['state' => 'blocked', 'reason' => 'policy_scope_or_revision'];
        }
        foreach (self::COVERAGE as $stage) {
            if (($policy['coverage'][$stage] ?? null) !== true) {
                return ['state' => 'blocked', 'reason' => 'incomplete_policy'];
            }
        }
        $deliveries = [];
        foreach ($policy['deliveries'] as $delivery) {
            if (! is_array($delivery)) {
                return ['state' => 'blocked', 'reason' => 'invalid_delivery'];
            }
            $keys = array_keys($delivery);
            sort($keys);
            if ($keys !== ['body', 'channel', 'destination', 'subject']
                || ! in_array($delivery['channel'], ['email', 'messenger', 'in_app'], true)) {
                return ['state' => 'blocked', 'reason' => 'invalid_delivery'];
            }
            foreach (['destination', 'subject', 'body'] as $field) {
                if (! is_string($delivery[$field]) || preg_match('//u', $delivery[$field]) !== 1) {
                    return ['state' => 'blocked', 'reason' => 'invalid_delivery'];
                }
            }
            if ($delivery['destination'] === '') {
                return ['state' => 'blocked', 'reason' => 'invalid_delivery'];
            }
            ksort($delivery);
            $key = hash('sha256', json_encode(['ticket-delivery-v1', $intent['operationKey'],
                $delivery['channel'], $delivery['destination']], JSON_THROW_ON_ERROR));
            if (isset($deliveries[$key]) && $deliveries[$key] !== $delivery) {
                return ['state' => 'blocked', 'reason' => 'conflicting_destination'];
            }
            $deliveries[$key] = $delivery;
        }
        ksort($deliveries);

        return ['state' => 'planned', 'operationKey' => $intent['operationKey'], 'intentDigest' => $digest,
            'policyRevision' => $policy['revision'], 'deliveries' => $deliveries];
    }
}
