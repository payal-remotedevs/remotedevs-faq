<?php
defined('TYPO3') || die();
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Routing\Router;

(static function() {
    ExtensionUtility::configurePlugin(
        'RdFaq',
        'Faqlist',
        [
            \RemoteDevs\RdFaq\Controller\FAQController::class => 'list',
            \RemoteDevs\RdFaq\Controller\CategoryController::class => 'list'
        ],
        // non-cacheable actions
        [
            \RemoteDevs\RdFaq\Controller\FAQController::class => 'list',
            \RemoteDevs\RdFaq\Controller\CategoryController::class => 'list'
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT

    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        'mod {
            wizards.newContentElement.wizardItems.faqplugintab {
                header = LLL:EXT:rd_faq/Resources/Private/Language/locallang_db.xlf:faqplugintab.header

                elements {
                    faqlist {
                        iconIdentifier = rdfaq-plugin-faqlist
                        title = LLL:EXT:rd_faq/Resources/Private/Language/locallang_db.xlf:tx_faq_faqlist.name
                        description = LLL:EXT:rd_faq/Resources/Private/Language/locallang_db.xlf:tx_faq_faqlist.description
                        tt_content_defValues {
                            CType = rdfaq_faqlist
                        }
                    }                    
                }
                show = *
            }
       }'
    );
})();

/** Page layout hooks to show preview of plugins  */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['tt_content_drawItem']['your_extension'] =
    \RemoteDevs\RdFaq\Hooks\PageLayoutViewItemHook::class;
