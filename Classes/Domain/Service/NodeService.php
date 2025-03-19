<?php
namespace SteinbauerIT\Neos\DeepLNodeTranslate\Domain\Service;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeVariation\Command\CreateNodeVariant;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\NodeType\NodeTypeNames;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindDescendantNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\NodeType\NodeTypeCriteria;
use Neos\ContentRepository\Core\Projection\ContentGraph\Nodes;
use Neos\ContentRepository\Core\Projection\ContentGraph\PropertyCollection;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class NodeService
{

    /**
     * @var NodeUriPathSegmentGenerator
     */
    #[Flow\Inject]
    protected $nodeUriPathSegmentGenerator;

    /**
     * @var DeepLService
     */
    #[Flow\Inject]
    protected $deepLService;

    /**
     * @var array
     */
    protected $settings = [];

    #[\Neos\Flow\Annotations\Inject]
    protected \Neos\ContentRepositoryRegistry\ContentRepositoryRegistry $contentRepositoryRegistry;

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
        foreach ($nodes as $node) {
            if($node->nodeTypeName === $nodeType) {
                $translatedProperties = $this->translateProperties($node->properties, $this->getDefinedPropertiesForNodeTypeFromConfiguration($node->nodeTypeName->value), $source, $target);
                $this->createTranslatedNode($node, $translatedProperties, $source, $target);
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
        $translatedProperties = $this->translateProperties($node->properties, $this->getDefinedPropertiesForNodeTypeFromConfiguration($node->nodeTypeName->value), $source, $target);
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
        $this->translateNode($startingPoint->aggregateId->value, $source, $target);
        foreach ($this->getNodesRecursive($startingPoint) as $nodeIdentifier) {
            $this->translateNode($nodeIdentifier, $source, $target);
        }
    }

    /**
     * @param \Neos\ContentRepository\Core\Projection\ContentGraph\Node $node
     * @param array $translatedProperties
     * @param array $source
     * @param array $target
     * @return void
     */
    private function createTranslatedNode(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $node, array $translatedProperties, array $source, array $target): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        );
        $targetDimensionSpacePoint = OriginDimensionSpacePoint::fromArray($target);

        foreach ($translatedProperties as $translatedPropertyKey => $translatedProperty) {
            if($node->hasProperty($translatedPropertyKey)) {
                if($translatedPropertyKey === 'uriPathSegment') {
                    $uriPathSegment = $this->nodeUriPathSegmentGenerator->generateUriPathSegment(null, $translatedProperty);
                    $translatedProperties[$translatedPropertyKey] = $uriPathSegment;
                }
            }
        }

        $contentRepository->handle(
            CreateNodeVariant::create(
                $node->workspaceName,
                $node->aggregateId,
                $node->originDimensionSpacePoint,
                $targetDimensionSpacePoint
            ),
        );

        $contentRepository->handle(
            SetNodeProperties::create(
                $node->workspaceName,
                $node->aggregateId,
                $targetDimensionSpacePoint,
                PropertyValuesToWrite::fromArray(
                    $translatedProperties
                )
            )
        );
    }

    /**
     * @param string $nodeType
     * @param array $source
     * @return Nodes
     */
    private function getNodesByNodeTypeAndDimensions(string $nodeType, array $source): Nodes
    {
        $subgraph = $this->contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        )->getContentGraph(
            WorkspaceName::forLive()
        )->getSubgraph(
            DimensionSpacePoint::fromArray($source),
            VisibilityConstraints::withoutRestrictions()
        );

        return $subgraph->findDescendantNodes(
            $subgraph->findRootNodeByType(
                NodeTypeName::fromString('Neos.Neos:Sites')
            )->aggregateId,
            FindDescendantNodesFilter::create(
                nodeTypes: NodeTypeCriteria::createWithAllowedNodeTypeNames(NodeTypeNames::fromStringArray([$nodeType])),
            )
        );
    }

    /**
     * @param string $nodeIdentifier
     * @param array $source
     * @return \Neos\ContentRepository\Core\Projection\ContentGraph\Node
     */
    private function getNodeByNodeIdentifierAndDimensions(string $nodeIdentifier, array $source): \Neos\ContentRepository\Core\Projection\ContentGraph\Node
    {
        $subgraph = $this->contentRepositoryRegistry->get(
            ContentRepositoryId::fromString('default')
        )->getContentGraph(
            WorkspaceName::forLive()
        )->getSubgraph(
            DimensionSpacePoint::fromArray($source),
            VisibilityConstraints::withoutRestrictions()
        );

        return $subgraph->findNodeById(
            NodeAggregateId::fromString($nodeIdentifier)
        );
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
     * @param PropertyCollection $nodeProperties
     * @param array $definedProperties
     * @param array $source
     * @param array $target
     * @return array
     */
    private function translateProperties(PropertyCollection $nodeProperties, array $definedProperties, array $source, array $target): array
    {
        $result = [];
        foreach ($definedProperties as $definedProperty) {
            if($nodeProperties->offsetGet($definedProperty) && is_string($nodeProperties->offsetGet($definedProperty))) {
                $result[$definedProperty] = $this->deepLService->translate(
                    $nodeProperties->offsetGet($definedProperty),
                    $source[array_key_first($source)],
                    $target[array_key_first($target)]
                );
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
     * @param \Neos\ContentRepository\Core\Projection\ContentGraph\Node $node
     * @return array
     */
    private function getNodesRecursive(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $node): array
    {
        $items = [];
        $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
        /** @var \Neos\ContentRepository\Core\Projection\ContentGraph\Node $childNode */
        foreach (iterator_to_array($subgraph->findChildNodes($node->aggregateId, FindChildNodesFilter::create())) as $childNode) {
            if($childNode->nodeTypeName->equals(
                NodeTypeName::fromString('Neos.Neos:Document')
            )) {
                if(count(iterator_to_array($subgraph->findChildNodes($childNode->aggregateId, FindChildNodesFilter::create()))) === 0) {
                    $items[] = $childNode;
                } else {
                    $items = array_merge($items, $this->getNodesRecursive($childNode));
                }
            }
        }
        return $items;
    }

}
