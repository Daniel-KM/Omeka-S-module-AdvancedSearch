<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2018-2025
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace AdvancedSearch\Site\Navigation\Link;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Site\Navigation\Link\LinkInterface;
use Omeka\Stdlib\ErrorStore;

class SearchingPage implements LinkInterface
{
    public function getName()
    {
        return 'Advanced search page';
    }

    public function getLabel(array $data, SiteRepresentation $site)
    {
        return $data['label'] ? $data['label'] : 'Search'; // @translate
    }

    public function isValid(array $data, ErrorStore $errorStore)
    {
        if (!isset($data['label'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: browse link missing label'); // @translate
            return false;
        }
        if (!isset($data['advancedsearch_config_id'])) {
            $errorStore->addError('o:navigation', 'Invalid navigation: browse link missing search page id'); // @translate
            return false;
        }
        return true;
    }

    public function getFormTemplate()
    {
        return 'common/navigation-link-form/searching-page';
    }

    public function toZend(array $data, SiteRepresentation $site)
    {
        $api = $site->getServiceLocator()->get('Omeka\ApiManager');
        try {
            $searchConfig = $api->read('search_configs', ['id' => $data['advancedsearch_config_id']])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return [
                'type' => 'uri',
                'uri' => null,
            ];
        }
        return [
            'label' => $data['label'],
            'route' => 'search-page-' . $searchConfig->slug(),
            'params' => [
                'site-slug' => $site->slug(),
            ],
        ];
    }

    public function toJstree(array $data, SiteRepresentation $site)
    {
        $label = $data['label'] ?? $site->title();
        $searchConfigId = $data['advancedsearch_config_id'] ?? null;
        return [
            'label' => $label,
            'advancedsearch_config_id' => $searchConfigId,
        ];
    }
}
