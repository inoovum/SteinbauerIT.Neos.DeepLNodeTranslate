<?php
namespace SteinbauerIT\Neos\DeepLNodeTranslate\Controller\Module;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Fusion\View\FusionView;
use SteinbauerIT\Neos\DeepLNodeTranslate\Domain\Service\NodeService;

final class DeepLController extends ActionController
{

    protected $defaultViewObjectName = FusionView::class;

    /**
     * @var NodeService
     */
    #[Flow\Inject]
    protected $nodeService;

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
     * @return void
     */
    public function indexAction(): void
    {

    }

    /**
     * @param string $nodeType
     * @param array $source
     * @param array $target
     * @return void
     */
    public function translateAction(string $nodeType, array $source, array $target): void
    {
        $this->nodeService->translateNodes($nodeType, $source, $target);
        $this->addFlashMessage('Nodes translated successfully');
        $this->redirect('index');
    }

}
