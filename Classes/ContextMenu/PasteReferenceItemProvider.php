<?php

declare(strict_types=1);

namespace EHAERER\PasteReference\ContextMenu;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\RecordProvider;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Type\Bitmask\JsConfirmation;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PasteReferenceItemProvider extends RecordProvider
{
    /**
     * @var array<non-empty-string, array<non-empty-string, mixed>>
     */
    protected $itemsConfiguration = [
        'pasteReference' => [
            'type' => 'item',
            'label' => 'LLL:EXT:paste_reference/Resources/Private/Language/locallang_db.xlf:tx_paste_reference_clickmenu_pastereference',
            'iconIdentifier' => 'actions-document-paste-after',
            'callbackAction' => 'pasteReference',
        ],
    ];

    /**
     * @return bool
     */
    public function canHandle(): bool
    {
        return $this->table === 'tt_content';
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 45;
    }

    /**
     * @param string $itemName
     * @return string[]
     * @throws RouteNotFoundException
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        $urlParameters = [
            'prErr' => 1,
            'uPT' => 1,
            'CB[paste]' => $this->table . '|' . -$this->record['uid'],
            'CB[pad]' => 'normal',
            'CB[update]' => [
                'colPos' => $this->record['colPos'],
            ],
            'reference' => 1,
        ];

        // Add needed EXT:container information to reference into a container
        if (($this->record['tx_container_parent'] ?? 0) > 0) {
            $urlParameters['CB[update]']['tx_container_parent'] = $this->record['tx_container_parent'];
        }

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $attributes = $this->getPasteAdditionalAttributes('after');
        $attributes += [
            'data-callback-module' => '@ehaerer/paste-reference/context-menu-actions',
            'data-action-url' => (string)$uriBuilder->buildUriFromRoute('tce_db', $urlParameters),
            'data-title' => $this->languageService->sL('LLL:EXT:paste_reference/Resources/Private/Language/locallang_db.xlf:newContentElementReference'),
            'data-message' => 'my message here',
        ];
        if ($this->backendUser->jsConfirmation(JsConfirmation::COPY_MOVE_PASTE)) {
            $selItem = $this->clipboard->getSelectedRecord();
            $title = $this->languageService->sL('LLL:EXT:paste_reference/Resources/Private/Language/locallang_db.xlf:newContentElementReference');
            $confirmMessage = sprintf(
                $this->languageService->sL('LLL:EXT:paste_reference/Resources/Private/Language/locallang_db.xlf:mess.reference_after'),
                GeneralUtility::fixed_lgd_cs($selItem['_RECORD_TITLE'], (int)$this->backendUser->uc['titleLen']),
                GeneralUtility::fixed_lgd_cs(BackendUtility::getRecordTitle($this->table, $this->record), (int)$this->backendUser->uc['titleLen'])
            );
            $attributes ['data-title'] = htmlspecialchars($title);
            $attributes ['data-message'] = htmlspecialchars($confirmMessage);
        }

        return $attributes;
    }

    /**
     * This method adds custom item to list of items generated by item providers with higher priority value (PageProvider)
     * You could also modify existing items here.
     * The new item is added after the 'info' item.
     * @see https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ApiOverview/Backend/ContextualMenu.html
     *
     * @param array<string, mixed> $items
     * @return array<string, mixed>
     */
    public function addItems(array $items): array
    {
        $this->initialize();

        $this->initDisabledItems();
        $localItems = $this->prepareItems($this->itemsConfiguration);

        if (isset($items['pasteAfter'])) {
            // @todo Instead of simple typecasting to (int) non integer return values of
            //       `array_search()` needs to be handled in a more appropriated way.
            $position = (int)array_search('pasteAfter', array_keys($items), true);
            $beginning = array_slice($items, 0, $position + 1, true);
            $end = array_slice($items, $position, null, true);
            $items = $beginning + $localItems + $end;
        } else {
            $items += $localItems;
        }
        return $items;
    }

    /**
     * @param string $itemName
     * @param string $type
     * @return bool
     */
    protected function canRender(string $itemName, string $type): bool
    {
        $canRender = false;
        if ($itemName === 'pasteReference') {
            $canRender = $this->canBePastedAfter()
                && $this->clipboard->currentMode() === 'copy'
                && $this->backendUser->checkAuthMode(
                    'tt_content',
                    'CType',
                    'shortcut'
                );
        }
        return $canRender;
    }
}
