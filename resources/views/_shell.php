<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, Main};
use UIAwesome\Html\Form\Button;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;

/**
 * @var string|null $actionUrl Optional adapter-defined action URL.
 * @var string $actionIcon Trusted bundled SVG markup for the action chip.
 * @var string $actionLabel Adapter-defined action label.
 * @var string $actionTitle Adapter-defined action tooltip.
 * @var string $content Rendered adapter-owned page content.
 * @var string $debugTheme Resolved theme, either `light` or `dark`.
 * @var string $historyUrl Debug history URL.
 * @var string $mode Adapter-defined page mode.
 * @var string|null $peakMemory Formatted peak memory or `null` when unavailable.
 * @var string $phpIcon Trusted bundled PHP SVG markup.
 * @var string $phpVersion PHP version label.
 * @var string $sidebar Rendered sidebar content.
 * @var string $themeIconMoon Trusted bundled moon SVG markup.
 * @var string $themeIconSun Trusted bundled sun SVG markup.
 * @var bool $useShell Whether to render the debugger shell.
 * @var string $yiiIcon Trusted bundled Yii SVG markup.
 * @var string $yiiVersion Yii version label.
 */
if ($useShell) {
    $chip = ['class' => 'yii-debug-brand-chip'];
    $themeIcon = $debugTheme === 'dark' ? $themeIconSun : $themeIconMoon;
    $themeAction = $debugTheme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme';

    $yiiChip = A::tag($chip)
        ->class('yii-debug-brand-chip-yii')
        ->href($historyUrl)
        ->html(
            Span::tag()->class('yii-debug-brand-icon')->html($yiiIcon),
            Span::tag()->class('yii-debug-brand-label')->content('Yii'),
            Span::tag()->class('yii-debug-brand-value')->content($yiiVersion),
        );
    $phpChip = Span::tag($chip)
        ->class('yii-debug-brand-chip-php')
        ->html(
            Span::tag()->class('yii-debug-brand-icon')->html($phpIcon),
            Span::tag()->class('yii-debug-brand-value')->content($phpVersion),
        );
    $memoryChip = $peakMemory === null
        ? ''
        : Span::tag($chip)
            ->class('yii-debug-brand-chip-mem')
            ->html(
                Span::tag()->class('yii-debug-brand-label')->content('Memory'),
                Span::tag()->class('yii-debug-brand-value')->content($peakMemory),
            );
    $actionIcon = Span::tag()
        ->addAriaAttribute('hidden', 'true')
        ->class('yii-debug-brand-icon')
        ->html($actionIcon);
    $actionLabel = Span::tag()->class('yii-debug-brand-label')->content($actionLabel);
    $actionChip = $actionUrl === null
        ? Span::tag($chip)
            ->addAriaAttribute('disabled', 'true')
            ->class('yii-debug-brand-chip-config is-disabled')
            ->html($actionIcon, $actionLabel)
            ->title($actionTitle)
        : A::tag($chip)
            ->addAriaAttribute('label', $actionTitle)
            ->class('yii-debug-brand-chip-config')
            ->href($actionUrl)
            ->html($actionIcon, $actionLabel)
            ->title($actionTitle);
    $themeChip = Button::tag($chip)
        ->addAriaAttribute('label', $themeAction)
        ->addAriaAttribute('pressed', $debugTheme === 'dark' ? 'true' : 'false')
        ->addDataAttribute('yii-debug-theme-toggle', true)
        ->class('yii-debug-brand-chip-theme')
        ->dataAttributes(
            [
                'current-theme' => $debugTheme,
                'icon-sun' => $themeIconSun,
                'icon-moon' => $themeIconMoon,
            ],
        )
        ->html(
            Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class('yii-debug-brand-icon')
                ->html($themeIcon),
        )
        ->title($themeAction)
        ->type('button');
    $densityChip = Button::tag($chip)
        ->addAriaAttribute('label', 'Switch to compact density')
        ->addAriaAttribute('pressed', 'false')
        ->addDataAttribute('yii-debug-density-toggle', true)
        ->class('yii-debug-brand-chip-control yii-debug-brand-chip-density')
        ->html(
            Span::tag()
                ->addDataAttribute('yii-debug-density-label', true)
                ->class('yii-debug-brand-label')
                ->content('Cozy'),
        )
        ->title('Switch to compact density')
        ->type('button');
    $copyChip = Button::tag($chip)
        ->addAriaAttribute('label', 'Copy debug link')
        ->addDataAttribute('yii-debug-copy-link', true)
        ->class('yii-debug-brand-chip-control yii-debug-brand-chip-copy')
        ->html(
            Span::tag()
                ->addAriaAttribute('live', 'polite')
                ->addDataAttribute('yii-debug-copy-label', true)
                ->class('yii-debug-brand-label')
                ->content('Copy link'),
        )
        ->title('Copy debug link')
        ->type('button');
    $header = Header::tag()
        ->class('yii-debug-brand-bar')
        ->html($yiiChip, $phpChip, $memoryChip, $actionChip, $densityChip, $copyChip, $themeChip);
    $skipLink = A::tag()
        ->class('yii-debug-skip-link')
        ->content('Skip to debug content')
        ->href('#yii-debug-main');
    $shell = Div::tag()
        ->class('yii-debug-page default-' . $mode)
        ->html(
            $skipLink,
            $header,
            Div::tag()
                ->class('yii-debug-layout')
                ->html(
                    $sidebar,
                    Main::tag()
                        ->addAttribute('tabindex', '-1')
                        ->id('yii-debug-main')
                        ->class('yii-debug-main yii-debug-card')
                        ->html($content),
                ),
        );
} else {
    $shell = Main::tag()
        ->addAttribute('tabindex', '-1')
        ->id('yii-debug-main')
        ->class('yii-debug-main-bare')
        ->html($content);
}
?>
<?= $shell->render();
