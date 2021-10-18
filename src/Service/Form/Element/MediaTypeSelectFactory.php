<?php declare(strict_types=1);

namespace AdvancedSearch\Service\Form\Element;

use AdvancedSearch\Form\Element\MediaTypeSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MediaTypeSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $list = $this->listMediaTypes($services);

        $element = new MediaTypeSelect;
        $element->setValueOptions($list);
        $element->setEmptyOption('Select media type…'); // @translate
        return $element;
    }

    protected function listMediaTypes(ContainerInterface $services)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $sql = <<<'SQL'
SELECT DISTINCT(media_type)
FROM media
WHERE media_type IS NOT NULL
    AND media_type != ""
ORDER BY media_type;
SQL;
        $result = $connection->executeQuery($sql)->fetchFirstColumn();
        return array_combine($result, $result);
    }
}
