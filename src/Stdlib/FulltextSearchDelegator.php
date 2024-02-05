<?php declare(strict_types=1);

namespace AdvancedSearch\Stdlib;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Omeka\Api\Adapter\AdapterInterface;
use Omeka\Api\ResourceInterface;
use PDO;
use Omeka\Stdlib\FulltextSearch;
use Omeka\Entity\Item;
use Omeka\Entity\Media;

/**
 * This delegator is skipped in factory when the option is not set.
 */
class FulltextSearchDelegator extends FulltextSearch
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $conn;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \Omeka\Stdlib\FulltextSearch
     */
    protected $realFulltextSearch;

    public function __construct(
        FulltextSearch $realFulltextSearch,
        Connection $connection,
        // For compatibility with Omeka S < v4.1.
        EntityManager $em,
        string $basePath
    ) {
        $this->realFulltextSearch = $realFulltextSearch;
        $this->conn = $connection;
        $this->em = $em;
        $this->basePath = $basePath;
    }

    /**
     * Include content of xml files Alto in full text search in item and media.
     *
     * @see \IiifServer\View\Helper\IiifAnnotationPageLine2
     *
     * {@inheritDoc}
     * @see \Omeka\Stdlib\FulltextSearch::save()
     */
    public function save(ResourceInterface $resource, AdapterInterface $adapter)
    {
        $this->realFulltextSearch->save($resource, $adapter);

        // TODO Add a specific index for full text in the internal database.

        if ($resource instanceof Item) {
            $text = '';
            foreach ($resource->getMedia() as $media) {
                $extracted = $this->extractText($media);
                if (strlen($extracted)) {
                    $text .= $extracted . "\n";
                }
            }
        } elseif ($resource instanceof Media) {
            $text = $this->extractText($resource);
        } else {
            return;
        }

        $text = trim($text);
        if (!strlen($text)) {
            return;
        }

        // Normally, the table data exists already for item, but not for media.

        // Copy of original save, except for value of text.

        $resourceId = $resource->getId();
        $resourceName = $adapter->getResourceName();
        $owner = $adapter->getFulltextOwner($resource);
        $ownerId = $owner ? $owner->getId() : null;

        $sql = 'INSERT INTO `fulltext_search` (
            `id`, `resource`, `owner_id`, `is_public`, `title`, `text`
        ) VALUES (
            :id, :resource, :owner_id, :is_public, :title, :text
        ) ON DUPLICATE KEY UPDATE
            `owner_id` = :owner_id, `is_public` = :is_public, `title` = :title, `text` = CONCAT(`text`, "\n", :text)';
        $stmt = $this->conn->prepare($sql);

        $stmt->bindValue('id', $resourceId, PDO::PARAM_INT);
        $stmt->bindValue('resource', $resourceName, PDO::PARAM_STR);
        $stmt->bindValue('owner_id', $ownerId, PDO::PARAM_INT);
        $stmt->bindValue('is_public', $adapter->getFulltextIsPublic($resource), PDO::PARAM_BOOL);
        $stmt->bindValue('title', $adapter->getFulltextTitle($resource), PDO::PARAM_STR);
        $stmt->bindValue('text', $text, PDO::PARAM_STR);
        $stmt->executeStatement();
    }

    protected function extractText(Media $media): string
    {
        if ($media->getMediaType() !== 'application/alto+xml') {
            return '';
        }

        // TODO Manage external storage.
        // Extract text from alto.
        $filepath = $this->basePath . '/original/' . $media->getFilename();
        if (!file_exists($filepath) || !is_readable($filepath)) {
            return '';
        }

        try {
            $xmlContent = file_get_contents($filepath);
            $xml = @simplexml_load_string($xmlContent);
        } catch (\Exception $e) {
            // No log.
            return '';
        }

        if (!$xml) {
            return '';
        }

        $namespaces = $xml->getDocNamespaces();
        $altoNamespace = $namespaces['alto'] ?? $namespaces[''] ?? 'http://www.loc.gov/standards/alto/ns-v4#';
        $xml->registerXPathNamespace('alto', $altoNamespace);

        $text = '';

        // TODO Use a single xpath or xsl to get the whole in one query.
        foreach ($xml->xpath('/alto:alto/alto:Layout//alto:TextLine') as $xmlTextLine) {
            /** @var \SimpleXMLElement $xmlString */
            foreach ($xmlTextLine->children() as $xmlString) {
                if ($xmlString->getName() === 'String') {
                    $attributes = $xmlString->attributes();
                    $text .= (string) @$attributes->CONTENT . ' ';
                }
            }
        }

        return trim($text);
    }
}
