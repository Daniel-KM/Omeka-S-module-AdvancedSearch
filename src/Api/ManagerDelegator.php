<?php declare(strict_types=1);

namespace AdvancedSearchPlus\Api;

class ManagerDelegator extends \Omeka\Api\Manager
{
    /**
     * Allows to override a search by property when initialize is false.
     *
     * Furthermore, fix "is_public".
     * @link https://github.com/omeka/omeka-s/pull/1671
     *
     * @see \Search\Api\ManagerDelegator::search()
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Manager::search()
     */
    public function search($resource, array $data = [], array $options = [])
    {
        if (isset($data['is_public']) && $data['is_public'] === '') {
            $data['is_public'] = null;
        }

        // Use the standard process when possible.
        if (array_key_exists('initialize', $options)
            && !$options['initialize']
            && in_array($resource, [
                'items',
                'media',
                'item_sets',
                'annotations',
                'generations',
            ])
            && !empty($data['property'])
        ) {
            $options['override'] = ['property' => $data['property']];
            unset($data['property']);
        }

        return parent::search($resource, $data, $options);
    }
}
