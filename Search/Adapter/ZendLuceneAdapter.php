<?php

namespace Massive\Bundle\SearchBundle\Search\Adapter;

use Massive\Bundle\SearchBundle\Search\AdapterInterface;
use Massive\Bundle\SearchBundle\Search\Document;
use Massive\Bundle\SearchBundle\Search\Factory;
use Massive\Bundle\SearchBundle\Search\Field;
use Massive\Bundle\SearchBundle\Search\QueryHit;
use Symfony\Component\Finder\Finder;
use ZendSearch\Lucene;

/**
 * Adapter for the ZendSearch library
 *
 * https://github.com/zendframework/ZendSearch
 * http://framework.zend.com/manual/1.12/en/zend.search.lucene.html 
 *   (docs for 1.2 version apply equally to 2.0)
 *
 * @author Daniel Leech <daniel@massive.com>
 */
class ZendLuceneAdapter implements AdapterInterface
{
    const ID_FIELDNAME = '__id';
    const URL_FIELDNAME = '__url';
    const TITLE_FIELDNAME = '__title';
    const DESCRIPTION_FIELDNAME = '__description';
    const CLASS_TAG = '__class';

    protected $basePath;
    protected $factory;

    /**
     * @param string $basePath Base filesystem path for the index
     */
    public function __construct(Factory $factory, $basePath)
    {
        $this->basePath = $basePath;
        $this->factory = $factory;
    }

    /**
     * Determine the index path for a given index name
     * @param string $indexName
     * @return string
     */
    protected function getIndexPath($indexName)
    {
        return sprintf('%s/%s', $this->basePath, $indexName);
    }

    /**
     * {@inheritDoc}
     */
    public function index(Document $document, $indexName)
    {
        $indexPath = $this->getIndexPath($indexName);

        if (!file_exists($indexPath)) {
            $index = Lucene\Lucene::create($indexPath);
        } else {
            $index = Lucene\Lucene::open($indexPath);

            // check to see if the subject already exists
            $this->removeExisting($index, $document);
        }

        $luceneDocument = new Lucene\Document();

        foreach ($document->getFields() as $field) {
            switch ($field->getType()) {
                case Field::TYPE_STRING:
                    $luceneField = Lucene\Document\Field::Text($field->getName(), $field->getValue());
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf(
                        'Search field type "%s" is not know. Known types are: %s',
                        implode(', ', Field::getValidTypes())
                    ));
            }

            $luceneDocument->addField($luceneField);
        }

        // add meta fields - used internally for showing the search results, etc.
        $luceneDocument->addField(Lucene\Document\Field::Keyword(self::ID_FIELDNAME, $document->getId()));
        $luceneDocument->addField(Lucene\Document\Field::Keyword(self::URL_FIELDNAME, $document->getUrl()));
        $luceneDocument->addField(Lucene\Document\Field::Keyword(self::TITLE_FIELDNAME, $document->getTitle()));
        $luceneDocument->addField(Lucene\Document\Field::Keyword(self::DESCRIPTION_FIELDNAME, $document->getDescription()));
        $luceneDocument->addField(Lucene\Document\Field::Keyword(self::CLASS_TAG, $document->getClass()));

        $index->addDocument($luceneDocument);
    }

    /**
     * Remove the existing entry for the given Document from the index, if it exists.
     *
     * @param Lucene\Index $index The Zend Lucene Index
     * @param Document $document The Massive Search Document
     */
    protected function removeExisting(Lucene\Index $index, Document $document)
    {
        $hits = $index->find(self::ID_FIELDNAME . ':' . $document->getId());

        foreach ($hits as $hit) {
            $index->delete($hit->id);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function search($queryString, array $indexNames = array())
    {
        $searcher = new Lucene\MultiSearcher();

        foreach ($indexNames as $indexName) {
            $searcher->addIndex(Lucene\Lucene::open($this->getIndexPath($indexName)));
        }

        $query = Lucene\Search\QueryParser::parse($queryString);

        $luceneHits = $searcher->find($query);

        $hits = array();

        foreach ($luceneHits as $luceneHit) {
            $hit = $this->factory->makeQueryHit();
            $document = $this->factory->makeDocument();

            $hit->setDocument($document);
            $hit->setScore($luceneHit->score);

            $luceneDocument = $luceneHit->getDocument();

            // map meta fields to document
            $document->setId($luceneDocument->getFieldValue(self::ID_FIELDNAME));
            $document->setTitle($luceneDocument->getFieldValue(self::TITLE_FIELDNAME));
            $document->setDescription($luceneDocument->getFieldValue(self::DESCRIPTION_FIELDNAME));
            $document->setUrl($luceneDocument->getFieldValue(self::URL_FIELDNAME));
            $document->setClass($luceneDocument->getFieldValue(self::CLASS_TAG));

            $hit->setId($document->getId());

            foreach ($luceneDocument->getFieldNames() as $fieldName) {
                $document->addField($this->factory->makeField($fieldName, $luceneDocument->getFieldValue($fieldName)));
            }
            $hits[] = $hit;
        }

        return $hits;
    }

    public function getStatus()
    {
        $finder = new Finder();
        $indexDirs = $finder->directories()->depth('== 0')->in($this->basePath);
        $status = array();

        foreach ($indexDirs as $indexDir) {
            $indexFinder = new Finder();
            $files = $indexFinder->files()->name('*')->depth('== 0')->in($indexDir->getPathname());
            $indexName = basename($indexDir);

            $index = Lucene\Lucene::open($this->getIndexPath($indexName));

            $indexStats = array(
                'size' => 0,
                'nb_files' => 0,
                'nb_documents' => $index->count()
            );

            foreach ($files as $file) {
                $indexStats['size'] += filesize($file);
                $indexStats['nb_files']++;
            }

            $status['idx:' . $indexName] = json_encode($indexStats);
        }

        return $status;
    }
}
