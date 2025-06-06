<?php
namespace RemoteDevs\RdFaq\Hooks;

use TYPO3\CMS\Backend\View\PageLayoutView;
use TYPO3\CMS\Backend\View\PageLayoutViewDrawItemHookInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class PageLayoutViewItemHook implements PageLayoutViewDrawItemHookInterface
{   
    private const LLPATH = 'LLL:EXT:rd_faq/Resources/Private/Language/locallang_be.xlf:';
    public array $flexformData = [];
    public array $data = [];
    protected IconFactory $iconFactory;

    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * Preprocess preview rendering of a content element
    */
    public function preProcess(
        PageLayoutView &$parentObject,
        &$drawItem,
        &$headerContent,
        &$itemContent,
        array &$row 
    ): void {
        if ($row['CType'] === 'rdfaq_faqlist') {
            // $itemContent = '<strong>FAQ Plugin Preview</strong><br>';
            // $itemContent .= 'Header: ' . htmlspecialchars($row['header'] ?? '') . '<br>';
            // $drawItem = false;

            $pluginName = $this->getPluginName($row);
            $flexFormData = $this->getFlexFormData((string)($row['pi_flexform'] ?? ''));

            $data = [];
            $this->setPluginPidConfig($data, $flexFormData, 'faqStoragePage');
            $this->setCategorySettings($data, $flexFormData);
            $this->setCategoryConjuction($data, $flexFormData);
            $this->setStoragePage($data, $flexFormData, 'settings.storagePage');
            $this->setOverrideDemandSettings($data, $flexFormData);
            $this->setOrderSettings($data, $flexFormData, 'settings.orderField', 'settings.orderDirection');
            $this->setPluginPaginationConfig($data, $flexFormData, 'settings.enablePagination', 'settings.displayItemsPerPage', 'settings.maxPagesNum', 'sDEF');

            $itemContent = $this->renderAsTable($data, $pluginName);
            $drawItem = false;
        }
    }

    /**
    * Returns the records flexform as array
    */
    protected function getFlexFormData(string $flexform): array
    {
        $flexFormData = GeneralUtility::xml2array($flexform);
        if (!is_array($flexFormData)) {
            $flexFormData = [];
        }
        return $flexFormData;
    }


    /**
     * Returns the plugin name
     */
    protected function getPluginName(array $record): string
    {
        $pluginId = str_replace('rdfaq_', '', $record['CType']);
        return htmlspecialchars($this->getLanguageService()->sL(self::LLPATH . 'plugin.' . $pluginId . '.title'));
    }
    /**
     * Sets the PID config for the configured PID settings in plugin flexform
     */
    protected function setPluginPidConfig(
        array &$data,
        array $flexFormData,
        string $pidSetting,
        string $sheet = 'sDEF'
    ): void {
        $pid = (int)$this->getFlexFormFieldValue($flexFormData, 'settings.' . $pidSetting, $sheet);
        if ($pid > 0) {
            $data[] = [
                'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.' . $pidSetting),
                'value' => $this->getRecordData($pid),
            ];
        }
    }
     /**
     * Get category settings
     */
    protected function setCategorySettings(array &$data, array $flexFormData): void
    {
        $categories = GeneralUtility::intExplode(',', $this->getFlexFormFieldValue($flexFormData, 'settings.categories'), true);
        if (count($categories) > 0) {
            $categoriesOut = [];
            foreach ($categories as $id) {
                $categoriesOut[] = $this->getRecordData($id, 'tx_rdfaq_domain_model_category');
            }

            $data[] = [
                'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.categories'),
                'value' => implode(', ', $categoriesOut),
            ];

            $includeSubcategories = $this->getFlexFormFieldValue($flexFormData, 'settings.includeSubcategories');
            if ((int)$includeSubcategories === 1) {
                $data[] = [
                    'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.includeSubcategories'),
                    'value' => 'icon',
                    'icon' => 'actions-check-square',
                ];
            }
        }
    }
     /**
     * Sets category conjunction if a category is selected
     */
    protected function setCategoryConjuction(array &$data, array $flexFormData): void
    {
        // If not category is selected, we do not need to display the category mode
        $categories = $this->getFlexFormFieldValue($flexFormData, 'settings.categories');
        if ($categories === null || $categories === '') {
            return;
        }

        $categoryConjunction = strtolower($this->getFlexFormFieldValue($flexFormData, 'settings.categoryModeConjunction') ?? '');


        switch ($categoryConjunction) {
            case 'or':
            case 'and':
            case 'notor':
            case 'notand':
                $text = htmlspecialchars($this->getLanguageService()->sL(
                    self::LLPATH . 'flexforms.plugin.field.categoryModeConjunction.' . $categoryConjunction
                ));
                break;
            default:
                $text = htmlspecialchars($this->getLanguageService()->sL(
                    self::LLPATH . 'flexforms.plugin.field.categoryModeConjunction.ignore'
                ));
                $text .= ' <span class="badge badge-warning">' . htmlspecialchars($this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.categories.possibleMisconfiguration')) . '</span>';
        }

        $data[] = [
            'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.categoryModeConjunction'),
            'value' => $text,
        ];
    }

    /**
     * Sets the storagePage configuration
     */
    protected function setStoragePage(array &$data, array $flexFormData, string $field): void
    {
        $value = $this->getFlexFormFieldValue($flexFormData, $field);

        if (!empty($value)) {
            $pageIds = GeneralUtility::intExplode(',', $value, true);
            $pagesOut = [];

            foreach ($pageIds as $id) {
                $pagesOut[] = $this->getRecordData($id, 'pages');
            }

            $recursiveLevel = (int)$this->getFlexFormFieldValue($flexFormData, 'settings.recursive');
            $recursiveLevelText = '';
            if ($recursiveLevel === 250) {
                $recursiveLevelText = $this->getLanguageService()->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:recursive.I.5');
            } elseif ($recursiveLevel > 0) {
                $recursiveLevelText = $this->getLanguageService()->sL('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:recursive.I.' . $recursiveLevel);
            }

            if (!empty($recursiveLevelText)) {
                $recursiveLevelText = '<br />' .
                    htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.recursive')) . ' ' .
                    $recursiveLevelText;
            }

            $data[] = [
                'title' => $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.startingpoint'),
                'value' => implode(', ', $pagesOut) . $recursiveLevelText,
            ];
        }
    }
     /**
     * Sets information to the data array if override demand setting is disabled
     */
    protected function setOverrideDemandSettings(array &$data, array $flexFormData): void
    {
        $field = (int)$this->getFlexFormFieldValue($flexFormData, 'settings.disableOverwriteDemand', 'additional');

        if ($field === 1) {
            $data[] = [
                'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.disableOverwriteDemand'),
                'value' => 'icon',
                'icon' => 'actions-check-square',
            ];
        }
    }


      /**
     * Sets the order settings
     */
    protected function setOrderSettings(
        array &$data,
        array $flexFormData,
        string $orderByField,
        string $orderDirectionField
    ): void {

        $orderField = $this->getFlexFormFieldValue($flexFormData, $orderByField);

        if (!empty($orderField)) {
            $text = $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.orderField.' . $orderField);

            // Order direction (asc, desc)
            $orderDirection = $this->getOrderDirectionSetting($flexFormData, $orderDirectionField);
            if ($orderDirection) {
                $text .= ', ' . strtolower($orderDirection);
            }

            $data[] = [
                'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.orderField'),
                'value' => $text,
            ];
        }
    }

     /**
     * Sets the Pagination settings
     */
    protected function setPluginPaginationConfig(
        array &$data,
        array $flexFormData,
        string $enablePagination,
        string $displayItemsPerPage,
        string $maxPagesNum,
        string $sheet
    ): void {

        $enablePagination = $this->getFlexFormFieldValue($flexFormData, $enablePagination,$sheet);
        
        $getDisplayItemsPerPage = $this->getFlexFormFieldValue($flexFormData, $displayItemsPerPage,$sheet);

        $getmaxPagesNum = $this->getFlexFormFieldValue($flexFormData, $maxPagesNum,$sheet);


        if (!empty($enablePagination)) {
            $text = $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.' . $enablePagination);
            $data[] = [
                'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.enablePagination'),
                'value' => 'icon',
                'icon' => 'actions-check-square',
            ];
        }

        if(!empty($getDisplayItemsPerPage)){
            $text = $getDisplayItemsPerPage;
            $data[] = [
                'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.displayItemsPerPage'),
                'value' => $text,
            ];
        }
        if(!empty($getmaxPagesNum)){
            $text = $getmaxPagesNum;
            $data[] = [
                'title' => $this->getLanguageService()->sL(self::LLPATH . 'flexforms.plugin.field.maxPagesNum'),
                'value' => $text,
            ];
        }
    }

    /**
     * Renders the given data and action as HTML table for plugin preview
     */
    protected function renderAsTable(array $data, string $pluginName = ''): string
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename('EXT:rd_faq/Resources/Private/PluginPreview/PageLayoutView.html');

        $view->assignMultiple([
            'data' => $data,
            'pluginName' => $pluginName,
        ]);

        return $view->render();
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Returns field value from flexform configuration, including checks if flexform configuration is available
     */
    protected function getFlexFormFieldValue(array $flexformData, string $key, string $sheet = 'sDEF'): ?string
    {
        return $flexformData['data'][$sheet]['lDEF'][$key]['vDEF'] ?? '';
    }
    
     /**
     * Returns the record data item
     */
    protected function getRecordData(int $id, string $table = 'pages'): string
    {
        $content = '';
        $record = BackendUtility::getRecord($table, $id);

        if (is_array($record)) {
            $data = '<span data-toggle="tooltip" data-placement="top" data-title="id=' . $record['uid'] . '">'
                . $this->iconFactory->getIconForRecord($table, $record, 'small')->render()
                . '</span> ';
            $content = BackendUtility::wrapClickMenuOnIcon($data, $table, $record['uid'], '', $record);

            $linkTitle = htmlspecialchars(BackendUtility::getRecordTitle($table, $record));
            $content .= $linkTitle;
        }

        return $content;
    }
     /**
     * Returns order direction
     */
    private function getOrderDirectionSetting(array $flexFormData, string $orderDirectionField): string
    {
        $text = '';

        $orderDirection = $this->getFlexFormFieldValue($flexFormData, $orderDirectionField);
        if (!empty($orderDirection)) {
            $text = $this->getLanguageService()->sL(
                self::LLPATH . 'flexforms.plugin.field.orderDirection.' . $orderDirection . 'ending'
            );
        }

        return $text;
    }
}
