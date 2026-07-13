<?php declare(strict_types=1);

namespace AdvancedSearchTest\Querier;

/**
 * Golden standard Omeka api queries for the querier parity checks.
 *
 * Single source shared by the development script
 * (data/scripts/compare-queriers.php, run against a real base with a Solr
 * core) and by the PHPUnit tests (deterministic fixtures, Internal engine in CI
 * and both engines when Solr is available). The queries are pure data, built
 * from a fixture array of handles, so the same list drives every check.
 */
class QuerierParityQueries
{
    /**
     * Build the golden queries from a fixture array.
     *
     * Each entry is [label => ['query' => apiQuery, 'ordered' => int|null]].
     * "ordered" is the number of first ids to compare in order (sorts);
     * otherwise the id sets are compared unordered. Fixture-dependent queries
     * (linked resource, dates, numeric) are added only when the matching
     * handles are present.
     *
     * Expected fixture handles: word, title_sample, subject, property_term,
     * second_term, ids, item_set_id, resource_class_id, resource_class_term,
     * resource_template_id, owner_id, media_type, linked_term, linked_id,
     * date_term, year, numeric_ts_pid, numeric_int_pid.
     */
    public static function all(array $fixture): array
    {
        $propTerm = $fixture['property_term'];
        $subject = $fixture['subject'];
        $word = $fixture['word'];
        $secondTerm = $fixture['second_term'] ?? null;

        $q = [
            // Scalars.
            'fulltext_search' => ['fulltext_search' => $word],
            'search_exact' => ['search' => $fixture['title_sample']],
            'id_list' => ['id' => $fixture['ids']],
            'item_set_id' => ['item_set_id' => $fixture['item_set_id']],
            'not_item_set_id' => ['not_item_set_id' => $fixture['item_set_id']],
            'resource_class_id' => ['resource_class_id' => $fixture['resource_class_id']],
            'resource_class_term' => ['resource_class_term' => $fixture['resource_class_term']],
            'resource_template_id' => ['resource_template_id' => $fixture['resource_template_id']],
            'owner_id' => ['owner_id' => $fixture['owner_id']],
            'in_sites' => ['in_sites' => true],
            'has_media_1' => ['has_media' => 1],
            'has_media_0' => ['has_media' => 0],
            // property[] types.
            'prop_eq' => ['property' => [['property' => $propTerm, 'type' => 'eq', 'text' => $subject]]],
            'prop_in' => ['property' => [['property' => $propTerm, 'type' => 'in', 'text' => mb_substr($subject, 0, 4)]]],
            'prop_nin' => ['property' => [['property' => $propTerm, 'type' => 'nin', 'text' => mb_substr($subject, 0, 4)]]],
            'prop_sw' => ['property' => [['property' => $propTerm, 'type' => 'sw', 'text' => mb_substr($subject, 0, 3)]]],
            'prop_ew' => ['property' => [['property' => $propTerm, 'type' => 'ew', 'text' => mb_substr($subject, -3)]]],
            'prop_ex' => ['property' => [['property' => $propTerm, 'type' => 'ex']]],
            'prop_nex' => ['property' => [['property' => $propTerm, 'type' => 'nex']]],
            'prop_any_in' => ['property' => [['property' => null, 'type' => 'in', 'text' => $word]]],
            'prop_or_two' => ['property' => [
                ['property' => $propTerm, 'type' => 'eq', 'text' => $subject],
                ['joiner' => 'or', 'property' => 'dcterms:title', 'type' => 'in', 'text' => $word],
            ]],
            // filter[] (AdvancedSearch), including on system fields (design
            // intent).
            'filter_eq' => ['filter' => [['field' => $propTerm, 'type' => 'eq', 'val' => $subject]]],
            'filter_list' => ['filter' => [['field' => $propTerm, 'type' => 'list', 'val' => [$subject]]]],
            'filter_system_item_set' => ['filter' => [['field' => 'item_set_id', 'type' => 'eq', 'val' => $fixture['item_set_id']]]],
            'filter_system_owner' => ['filter' => [['field' => 'owner_id', 'type' => 'eq', 'val' => $fixture['owner_id']]]],
            // Remaining types.
            'prop_neq' => ['property' => [['property' => $propTerm, 'type' => 'neq', 'text' => $subject]]],
            'prop_lt' => ['property' => [['property' => $propTerm, 'type' => 'lt', 'text' => $subject]]],
            'prop_gte' => ['property' => [['property' => $propTerm, 'type' => 'gte', 'text' => $subject]]],
            'prop_ma' => ['property' => [['property' => $propTerm, 'type' => 'ma', 'text' => '^' . preg_quote(mb_substr($subject, 0, 3))]]],
            // Precedence: A and B or C must be (A∧B)∨C like sql.
            'prop_and_or_mix' => ['property' => [
                ['property' => $propTerm, 'type' => 'ex'],
                ['joiner' => 'and', 'property' => 'dcterms:title', 'type' => 'in', 'text' => $word],
                ['joiner' => 'or', 'property' => $propTerm, 'type' => 'eq', 'text' => $subject],
            ]],
            'prop_or_nex' => ['property' => [
                ['property' => $propTerm, 'type' => 'eq', 'text' => $subject],
                ['joiner' => 'or', 'property' => $secondTerm ?: $propTerm, 'type' => 'nex'],
            ]],
            // Args resolved through maps that may be absent from the core.
            'has_original_1' => ['has_original' => 1],
            'has_thumbnails_1' => ['has_thumbnails' => 1],
            // Behavior check: an arg unknown to both sides. The api ignores it.
            'unhandled_arg' => ['zzz_unknown_arg' => 'x'],
            // Multi-field row = aggregated alias: the api makes an OR on the
            // properties of the row.
            'filter_multifield' => ['filter' => [[
                'field' => array_values(array_filter([$propTerm, $secondTerm])),
                'type' => 'in',
                'val' => mb_substr($subject, 0, 4),
            ]]],
            // Combined.
            'combo_set_class' => [
                'item_set_id' => $fixture['item_set_id'],
                'resource_class_id' => $fixture['resource_class_id'],
            ],
            'combo_fulltext_set' => [
                'fulltext_search' => $word,
                'item_set_id' => $fixture['item_set_id'],
            ],
            // Sorts: ordered comparison on the first page.
            'sort_created_desc' => ['sort_by' => 'created', 'sort_order' => 'desc', '__ordered' => 10],
            'sort_title_asc' => ['sort_by' => 'title', 'sort_order' => 'asc', '__ordered' => 10],
        ];

        if (!empty($fixture['media_type'])) {
            $q['media_types'] = ['media_types' => $fixture['media_type']];
        }
        if (!empty($fixture['linked_term']) && !empty($fixture['linked_id'])) {
            $q['prop_res'] = ['property' => [['property' => $fixture['linked_term'], 'type' => 'res', 'text' => $fixture['linked_id']]]];
            $q['prop_nres'] = ['property' => [['property' => $fixture['linked_term'], 'type' => 'nres', 'text' => $fixture['linked_id']]]];
        }
        if (!empty($fixture['date_term']) && !empty($fixture['year'])) {
            $q['prop_yreq'] = ['property' => [['property' => $fixture['date_term'], 'type' => 'yreq', 'text' => $fixture['year']]]];
            $q['prop_yrlte'] = ['property' => [['property' => $fixture['date_term'], 'type' => 'yrlte', 'text' => $fixture['year']]]];
        }
        if (!empty($fixture['numeric_ts_pid'])) {
            $q['numeric_ts_gte'] = ['numeric' => ['ts' => ['gte' => ['pid' => $fixture['numeric_ts_pid'], 'val' => '1918-01-01']]]];
            $q['numeric_ts_lt'] = ['numeric' => ['ts' => ['lt' => ['pid' => $fixture['numeric_ts_pid'], 'val' => '1918']]]];
        }
        if (!empty($fixture['numeric_int_pid'])) {
            $q['numeric_int_gt'] = ['numeric' => ['int' => ['gt' => ['pid' => $fixture['numeric_int_pid'], 'val' => 42]]]];
        }

        // Normalize to [query, ordered].
        $out = [];
        foreach ($q as $label => $query) {
            $ordered = null;
            if (isset($query['__ordered'])) {
                $ordered = (int) $query['__ordered'];
                unset($query['__ordered']);
            }
            $out[$label] = ['query' => $query, 'ordered' => $ordered];
        }
        return $out;
    }
}
