<?php declare(strict_types=1);

/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2020-2024
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

namespace AdvancedSearch\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function browseAction()
    {
        $api = $this->api();
        $engines = $api->search('search_engines', ['sort_by' => 'name'])->getContent();
        $searchConfigs = $api->search('search_configs', ['sort_by' => 'name'])->getContent();
        $suggesters = $api->search('search_suggesters', ['sort_by' => 'name'])->getContent();

        // For simplicity in settings management, the list of all search configs
        // is stored one time here.
        $searchConfigPaths = [];
        foreach ($searchConfigs as $searchConfig) {
            $searchConfigPaths[$searchConfig->id()] = $searchConfig->path();
        }
        $this->settings()->set('advancedsearch_all_configs', $searchConfigPaths);

        return new ViewModel([
            'engines' => $engines,
            'searchConfigs' => $searchConfigs,
            'suggesters' => $suggesters,
        ]);
    }
}
