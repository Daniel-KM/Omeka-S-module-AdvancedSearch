<?php declare(strict_types=1);

namespace AdvancedSearch\Form\Admin;

use Laminas\Form\Element;
use Laminas\Form\Form;

class SearchConfigImportForm extends Form
{
    public function init(): void
    {
        $this
            ->setAttribute('enctype', 'multipart/form-data')
            ->add([
                'name' => 'import_file',
                'type' => Element\File::class,
                'options' => [
                    'label' => 'Config file (yaml or json)', // @translate
                ],
                'attributes' => [
                    'id' => 'import_file',
                    'accept' => '.yaml,.yml,.json,application/yaml,application/json,text/plain',
                ],
            ])
            ->add([
                'name' => 'import_text',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Or paste the config (yaml or json)', // @translate
                ],
                'attributes' => [
                    'id' => 'import_text',
                    'rows' => 12,
                    'placeholder' => "results:\n  label: Search results\nfacet:\n  facets:\n    …",
                ],
            ]);

        $this->getInputFilter()
            ->add(['name' => 'import_file', 'required' => false])
            ->add(['name' => 'import_text', 'required' => false]);
    }
}
