<?php

/*
 * This file is part of the Textmaster Api v1 client package.
 *
 * (c) Christian Daguerre <christian@daguer.re>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Textmaster\Translator;

use Textmaster\Exception\InvalidArgumentException;
use Textmaster\Exception\UnexpectedTypeException;
use Textmaster\Model\DocumentInterface;
use Textmaster\Translator\Factory\DocumentFactoryInterface;
use Textmaster\Translator\Provider\MappingProviderInterface;

class Translator implements TranslatorInterface
{
    /**
     * @var Adapter\AdapterInterface[]
     */
    protected $adapters;

    /**
     * @var MappingProviderInterface
     */
    protected $mappingProvider;

    /**
     * @var DocumentFactoryInterface|null
     */
    protected $documentFactory;

    /**
     * Constructor.
     *
     * @param Adapter\AdapterInterface[]    $adapters
     * @param MappingProviderInterface      $mappingProvider
     * @param DocumentFactoryInterface|null $documentFactory
     */
    public function __construct(
        array $adapters,
        MappingProviderInterface $mappingProvider,
        DocumentFactoryInterface $documentFactory = null
    ) {
        $this->adapters = $adapters;
        $this->mappingProvider = $mappingProvider;
        $this->documentFactory = $documentFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function push($subject, $documentOrParams = null, $save = true)
    {
        $document = $documentOrParams;

        if (!$document instanceof DocumentInterface) {
            $document = $this->getDocumentFactory()->createDocument($subject, $documentOrParams);
        }

        $properties = $this->mappingProvider->getProperties($subject);

        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($subject)) {
                $document = $adapter->push($subject, $properties, $document);

                if ($save) {
                    $document->save();
                }

                return $document;
            }
        }

        throw new InvalidArgumentException(sprintf('No adapter found for "%s".', get_class($subject)));
    }

    /**
     * {@inheritdoc}
     */
    public function compare(DocumentInterface $document)
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->compare($document);
            } catch (UnexpectedTypeException $e) {
                continue;
            }
        }

        throw new InvalidArgumentException(sprintf('No adapter found for document "%s".', $document->getId()));
    }

    /**
     * {@inheritdoc}
     */
    public function complete(DocumentInterface $document, $satisfaction = null, $message = null)
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->complete($document, $satisfaction, $message);
            } catch (UnexpectedTypeException $e) {
                continue;
            }
        }

        throw new InvalidArgumentException(sprintf('No adapter found for document "%s".', $document->getId()));
    }

    /**
     * {@inheritdoc}
     */
    public function pull(DocumentInterface $document)
    {
        foreach ($this->adapters as $adapter) {
            try {
                return $adapter->pull($document);
            } catch (UnexpectedTypeException $e) {
                continue;
            }
        }

        throw new InvalidArgumentException(sprintf('No adapter found for document "%s".', $document->getId()));
    }

    /**
     * {@inheritdoc}
     */
    public function getSubjectFromDocument(DocumentInterface $document)
    {
        foreach ($this->adapters as $adapter) {
            try {
                $translatable = $adapter->getSubjectFromDocument($document);
                if (null !== $translatable) {
                    return $translatable;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        throw new InvalidArgumentException(sprintf('No subject for document "%s"', $document->getId()));
    }

    /**
     * Get document factory.
     *
     * @return DocumentFactoryInterface
     */
    private function getDocumentFactory()
    {
        if (null === $this->documentFactory) {
            throw new InvalidArgumentException('No document factory provided.');
        }

        return $this->documentFactory;
    }
}
