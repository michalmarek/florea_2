<?php

declare(strict_types=1);

namespace Core\Forms;

use Nette\Forms\Form;
use Nette\Forms\Controls\SubmitButton;
use Core\Config;

/**
 * FormFactory - Továrna pro vytváření formulářů
 *
 * Poskytuje centralizované místo pro vytváření a konfiguraci formulářů.
 * Všechny formuláře vytvořené touto továrnou mají:
 * - Bootstrap 5 renderování
 * - CSRF ochranu
 * - Jednotný design a chování
 *
 * Metody:
 * - create() - Vytvoří nový formulář s výchozím nastavením
 * - addCsrfProtection(Form) - Přidá CSRF ochranu
 * - setBootstrapRenderer(Form) - Nastaví Bootstrap 5 renderer
 *
 * Použití v Presenteru:
 * protected function createComponentContactForm(): Form
 * {
 *     $form = FormFactory::create();
 *     $form->addText('name', 'Jméno:')
 *         ->setRequired('Vyplňte své jméno');
 *     $form->addEmail('email', 'Email:')
 *         ->setRequired('Vyplňte email');
 *     $form->addSubmit('send', 'Odeslat');
 *     $form->onSuccess[] = [$this, 'contactFormSucceeded'];
 *     return $form;
 * }
 *
 * @example
 * // Jednoduchý kontaktní formulář
 * $form = FormFactory::create();
 * $form->addText('name')->setRequired();
 * $form->addEmail('email')->setRequired();
 * $form->onSuccess[] = function(Form $form, $values) {
 *     // zpracování
 * };
 */
class FormFactory
{
    /**
     * Vytvoří nový formulář s výchozím nastavením
     */
    public static function create(): Form
    {
        $form = new Form;

        // CSRF ochrana
        self::addCsrfProtection($form);

        // Bootstrap 5 rendering
        self::setBootstrapRenderer($form);

        return $form;
    }

    /**
     * Přidá CSRF ochranu do formuláře
     */
    public static function addCsrfProtection(Form $form): void
    {
        $form->addProtection('Vypršela časová platnost formuláře. Odešlete jej prosím znovu.');
    }

    /**
     * Nastaví Bootstrap 5 renderer
     */
    public static function setBootstrapRenderer(Form $form): void
    {
        $renderer = $form->getRenderer();
        $renderer->wrappers['controls']['container'] = null;
        $renderer->wrappers['pair']['container'] = 'div class="mb-3"';
        $renderer->wrappers['pair']['.error'] = 'has-danger';
        $renderer->wrappers['control']['container'] = 'div';
        $renderer->wrappers['label']['container'] = 'div';
        $renderer->wrappers['control']['description'] = 'span class="form-text"';
        $renderer->wrappers['control']['errorcontainer'] = 'span class="invalid-feedback d-block"';
        $renderer->wrappers['error']['container'] = 'div class="alert alert-danger"';
        $renderer->wrappers['error']['item'] = 'p';

        // Úprava HTML elementů
        $form->onRender[] = function (Form $form) {
            foreach ($form->getControls() as $control) {
                $type = $control->getOption('type');

                if ($control instanceof SubmitButton) {
                    $control->getControlPrototype()->addClass('btn btn-primary');
                } elseif ($type === 'text' || $type === 'email' || $type === 'password' || $type === 'tel') {
                    $control->getControlPrototype()->addClass('form-control');
                } elseif ($type === 'textarea') {
                    $control->getControlPrototype()->addClass('form-control');
                } elseif ($type === 'select') {
                    $control->getControlPrototype()->addClass('form-select');
                } elseif ($type === 'checkbox') {
                    $control->getControlPrototype()->addClass('form-check-input');
                    $control->getLabelPrototype()->addClass('form-check-label');
                    // Wrap checkbox v Bootstrap struktuře
                    $control->setOption('id', $control->getHtmlId());
                } elseif ($type === 'radio') {
                    $control->getControlPrototype()->addClass('form-check-input');
                    $control->getLabelPrototype()->addClass('form-check-label');
                }

                // Přidání is-invalid třídy při chybě
                if ($control->hasErrors()) {
                    $control->getControlPrototype()->addClass('is-invalid');
                }
            }
        };
    }
}
