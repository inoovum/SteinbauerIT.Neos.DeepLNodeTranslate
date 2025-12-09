<?php
namespace SteinbauerIT\Neos\DeepLNodeTranslate\Domain\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Neos\Flow\Annotations as Flow;

use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Eel\FlowQuery\Operations;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Service\NodeService as NeosNodeService;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Utility\Now;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;

class NodeService
{

    /**
     * @Flow\Inject
     * @var ContextFactoryInterface
     */
    protected $contextFactory;

    /**
     * @Flow\Inject
     * @var NodeTypeManager
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var NeosNodeService
     */
    protected $nodeService;

    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    /**
     * @Flow\Inject
     * @var ContentDimensionPresetSourceInterface
     */
    protected $contentDimensionPresetSource;

    /**
     * @Flow\Inject
     * @var DeepLService
     */
    protected $deepLService;

    /**
     * @var array
     */
    protected $settings = [];

    /**
     * @param array $settings
     * @return void
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * @param string $nodeType
     * @param array $source
     * @param array $target
     * @return void
     */
    public function translateNodes(string $nodeType, array $source, array $target): void
    {
        $nodes = $this->getNodesByNodeTypeAndDimensions($nodeType, $source);
        if(!empty($nodes)) {
            foreach ($nodes as $node) {
                $translatedProperties = $this->translateProperties((array) $node->getProperties(), $this->getDefinedPropertiesForNodeTypeFromConfiguration($node->getNodeType()->getName()), $source, $target);
                $this->createTranslatedNode($node, $translatedProperties, $source, $target);
                sleep(5);
            }
        }
    }

    /**
     * @param string $nodeIdentifier
     * @param array $source
     * @param array $target
     * @return void
     */
    public function translateNode(string $nodeIdentifier, array $source, array $target): void
    {
        $node = $this->getNodeByNodeIdentifierAndDimensions($nodeIdentifier, $source);
        $translatedProperties = $this->translateProperties((array) $node->getProperties(), $this->getDefinedPropertiesForNodeTypeFromConfiguration($node->getNodeType()->getName()), $source, $target);
        $this->createTranslatedNode($node, $translatedProperties, $source, $target);
    }

    /**
     * @param string $nodeIdentifier
     * @param array $source
     * @param array $target
     * @return void
     */
    public function translateNodeAndTheirChildren(string $nodeIdentifier, array $source, array $target): void
    {
        $startingPoint = $this->getNodeByNodeIdentifierAndDimensions($nodeIdentifier, $source);

        $this->translateNode($startingPoint->getIdentifier(), $source, $target);

        foreach ($this->getNodesRecursive($startingPoint) as $nodeIdentifier) {
            $this->translateNode($nodeIdentifier, $source, $target);
        }
    }

    /**
     * @param Node $node
     * @param array $translatedProperties
     * @param array $source
     * @param array $target
     * @return void
     */
    private function createTranslatedNode(Node $node, array $translatedProperties, array $source, array $target): void
    {
        $context = $this->contextFactory->create(
            [
                'workspaceName' => 'live',
                'currentDateTime' => new Now(),
                'dimensions' => array_merge_recursive($target, $source),
                'targetDimensions' => [array_key_first($target) => $target[array_key_first($target)][array_key_first($target[array_key_first($target)])]]
            ]
        );
        $targetNode = $context->getNodeByIdentifier($node->getNodeAggregateIdentifier()->__toString());
        if($targetNode !== null) {
            $newNode = $context->adoptNode($targetNode);
            if(!empty($translatedProperties)) {
                foreach ($translatedProperties as $translatedPropertyKey => $translatedProperty) {
                    if($newNode->hasProperty($translatedPropertyKey)) {
                        if($translatedPropertyKey === 'uriPathSegment') {
                            $uriPathSegment = $this->nodeUriPathSegmentGenerator->generateUriPathSegment(null, $translatedProperty);
                            $newNode->setProperty($translatedPropertyKey, $uriPathSegment);
                        } else {
                            $newNode->setProperty($translatedPropertyKey, $translatedProperty);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $nodeType
     * @param array $source
     * @return array
     */
    private function getNodesByNodeTypeAndDimensions(string $nodeType, array $source): array
    {
        $context = $this->contextFactory->create(
            [
                'workspaceName' => 'live',
                'currentDateTime' => new Now(),
                'dimensions' => [$source]
            ]
        );
        return (new FlowQuery(array($context->getCurrentSiteNode())))->find('[instanceof ' . $nodeType .']')->context(['workspaceName' => 'live'])->sort('_index', 'ASC')->get();
    }

    /**
     * @param string $nodeIdentifier
     * @param array $source
     * @return NodeInterface
     */
    private function getNodeByNodeIdentifierAndDimensions(string $nodeIdentifier, array $source): NodeInterface
    {
        $context = $this->contextFactory->create(
            [
                'workspaceName' => 'live',
                'currentDateTime' => new Now(),
                'dimensions' => [$source]
            ]
        );
        return $context->getNodeByIdentifier($nodeIdentifier);
    }

    /**
     * @param string $nodeType
     * @return array
     */
    private function getDefinedPropertiesForNodeTypeFromConfiguration(string $nodeType): array
    {
        $nodeTypes = $this->settings['nodeTypes'];
        if(!empty($nodeTypes)) {
            foreach ($nodeTypes as $itemKey => $item) {
                if($itemKey === $nodeType) {
                    return $item['properties'];
                }
            }
        }
        return [];
    }

    /**
     * @param array $nodeProperties
     * @param array $definedProperties
     * @param array $source
     * @param array $target
     * @return array
     */
    private function translateProperties(array $nodeProperties, array $definedProperties, array $source, array $target): array
    {
        $result = [];
        foreach ($definedProperties as $definedProperty) {
            if(array_key_exists($definedProperty, $nodeProperties)) {
                if(is_string($nodeProperties[$definedProperty])) {
                    $result[$definedProperty] = $this->deepLService->translate($nodeProperties[$definedProperty], $source[array_key_first($source)][array_key_first($source[array_key_first($source)])], $target[array_key_first($target)][array_key_first($target[array_key_first($target)])]);
                }
            }
        }
        return $result;
    }

    /**
     * @param array $source
     * @param array $target
     * @param string $nodeIdentifier
     * @return void
     */
    public function translateInline(array $source, array $target, string $nodeIdentifier): void
    {
        $this->translateNode($nodeIdentifier, $source, $target);
    }

    /**
     * @param NodeInterface $node
     * @return array
     */
    private function getNodesRecursive(NodeInterface $node): array
    {
        $items = [];
        foreach ($node->getChildNodes() as $childNode) {
            if(!$childNode->getNodeType()->isOfType('Neos.Neos:Document')) {
                if(count($childNode->getChildNodes()) === 0) {
                    $items[] = $childNode->getIdentifier();
                } else {
                    $items = array_merge($items, $this->getNodesRecursive($childNode));
                }
            }
        }
        return $items;
    }

}
