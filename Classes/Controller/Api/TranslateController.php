<?php
namespace SteinbauerIT\Neos\DeepLNodeTranslate\Controller\Api;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\View\JsonView;
use SteinbauerIT\Neos\DeepLNodeTranslate\Domain\Service\NodeService;

final class TranslateController extends ActionController
{

    protected $defaultViewObjectName = JsonView::class;

    /**
     * @var NodeService
     */
    #[Flow\Inject]
    protected $nodeService;

    /**
     * @param string $nodeType
     * @param array $source
     * @param array $target
     * @param string $nodeIdentifier
     * @return void
     */
    #[Flow\SkipCsrfProtection]
    public function translateAction(string $nodeType, array $source, array $target, string $nodeIdentifier): void
    {
        $this->nodeService->translateInline(json_decode($source[0], true), json_decode($target[0], true), $nodeIdentifier);
        $this->view->assign('value', ['response' => true]);
    }

    /**
     * @param string $nodeIdentifier
     * @param string $sourceDimensionKey
     * @param string $sourceDimension
     * @param string $targetDimensionKey
     * @param string $targetDimension
     * @return void
     */
    #[Flow\SkipCsrfProtection]
    public function translateNodeAndTheirChildrenByIdentifierAction(string $nodeIdentifier, string $sourceDimensionKey, string $sourceDimension, string $targetDimensionKey, string $targetDimension): void
    {
        $this->nodeService->translateNodeAndTheirChildren($nodeIdentifier, [$sourceDimensionKey => $sourceDimension], [$targetDimensionKey => $targetDimension]);
        $this->view->assign('value', ['response' => true]);
    }

}
