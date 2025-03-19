<?php
namespace SteinbauerIT\Neos\DeepLNodeTranslate\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use SteinbauerIT\Neos\DeepLNodeTranslate\Domain\Service\NodeService;

#[Flow\Scope('singleton')]
class TranslateCommandController extends CommandController
{

    /**
     * @var NodeService
     */
    #[Flow\Inject]
    protected $nodeService;

    /**
     * @var array
     */
    #[Flow\InjectConfiguration(package: 'SteinbauerIT.Neos.DeepLNodeTranslate', path: 'nodeTypes')]
    protected $nodeTypes = [];

    /**
     * Translate nodes
     *
     * @param string $nodeType
     * @param string $sourceDimensionKey
     * @param string $sourceDimension
     * @param string $targetDimensionKey
     * @param string $targetDimension
     * @return void
     */
    public function nodesCommand(string $nodeType, string $sourceDimensionKey, string $sourceDimension, string $targetDimensionKey, string $targetDimension)
    {
        $this->nodeService->translateNodes($nodeType, [$sourceDimensionKey => $sourceDimension], [$targetDimensionKey => $targetDimension]);
        $this->outputLine($nodeType . ' nodes translated from ' . $sourceDimension . ' to ' . $targetDimension);
    }

    /**
     * Translate allnodes
     *
     * @param string $sourceDimensionKey
     * @param string $sourceDimension
     * @param string $targetDimensionKey
     * @param string $targetDimension
     * @return void
     */
    public function allNodesCommand(string $sourceDimensionKey, string $sourceDimension, string $targetDimensionKey, string $targetDimension)
    {
        $nodeTypes = $this->nodeTypes;
        foreach ($nodeTypes as $nodeTypeKey => $nodeType) {
            $this->nodeService->translateNodes($nodeTypeKey, [$sourceDimensionKey => $sourceDimension], [$targetDimensionKey => $targetDimension]);
            $this->outputLine($nodeTypeKey . ' nodes translated from ' . $sourceDimension . ' to ' . $targetDimension);
        }
    }

    /**
     * Translate node
     *
     * @param string $nodeIdentifier
     * @param string $sourceDimensionKey
     * @param string $sourceDimension
     * @param string $targetDimensionKey
     * @param string $targetDimension
     * @return void
     */
    public function nodeCommand(string $nodeIdentifier, string $sourceDimensionKey, string $sourceDimension, string $targetDimensionKey, string $targetDimension)
    {
        $this->nodeService->translateNode($nodeIdentifier, [$sourceDimensionKey => $sourceDimension], [$targetDimensionKey => $targetDimension]);
        $this->outputLine($nodeIdentifier . ' translated from ' . $sourceDimension . ' to ' . $targetDimension);
    }

}
