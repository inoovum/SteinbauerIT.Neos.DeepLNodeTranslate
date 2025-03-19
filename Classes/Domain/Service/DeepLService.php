<?php
namespace SteinbauerIT\Neos\DeepLNodeTranslate\Domain\Service;

use Neos\Flow\Annotations as Flow;
use DeepL\Translator;

class DeepLService
{

    /**
     * @var string
     */
    #[Flow\InjectConfiguration(package: 'SteinbauerIT.Neos.DeepLNodeTranslate', path: 'authKey')]
    protected $authKey;

    /**
     * @var array
     */
    #[Flow\InjectConfiguration(package: 'SteinbauerIT.Neos.DeepLNodeTranslate', path: 'prefer')]
    protected $prefer = [];

    /**
     * @var array
     */
    #[Flow\InjectConfiguration(package: 'SteinbauerIT.Neos.DeepLNodeTranslate', path: 'normalizeSource')]
    protected $normalizeSource = [];

    /**
     * @param string $sourceValue
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @return string
     */
    public function translate(string $sourceValue, string $sourceLanguage, string $targetLanguage): string
    {
        $translator = new Translator($this->authKey);
        if(!empty($sourceValue)) {
            $result = $translator->translateText($sourceValue, $this->normalizeSource($sourceLanguage), $this->setPreferredLanguageShortcut($targetLanguage), ['tag_handling' => 'html']);
            return $result->text;
        }
        return $sourceValue;
    }

    /**
     * @param string $targetLanguage
     * @return string
     */
    private function setPreferredLanguageShortcut(string $targetLanguage): string
    {
        if(array_key_exists($targetLanguage, $this->prefer)) {
            return $this->prefer[$targetLanguage];
        }
        return $targetLanguage;
    }

    /**
     * @param string $sourceLanguage
     * @return string
     */
    private function normalizeSource(string $sourceLanguage): string
    {
        if(array_key_exists($sourceLanguage, $this->normalizeSource)) {
            return $this->normalizeSource[$sourceLanguage];
        }
        return $sourceLanguage;
    }

}
