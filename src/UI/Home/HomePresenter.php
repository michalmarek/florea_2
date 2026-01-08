<?php

declare(strict_types=1);

namespace UI\Home;

use UI\Base\BasePresenter;
use Core\Config;

class HomePresenter extends BasePresenter
{
    /**
     * Akce pro homepage
     */
    public function actionDefault(): void
    {
        // Příprava dat pro šablonu
        $this->assign('title', $this->getLocalizedTitle());
        $this->assign('welcomeMessage', $this->getWelcomeMessage());

        // Příklad načtení dalších dat
        $this->assign('featuredArticles', $this->getFeaturedArticles());
        $this->assign('stats', $this->getStats());

        // Vytvoř formulář už v action (ne až při renderování)
        $testForm = $this->getComponent('testForm');
        $this->assign('testForm', $testForm);

        //        $this->flashMessage('Úspěch!', 'success');
    }

    /**
     * Render homepage
     */
    public function renderDefault(): void
    {
        // Automaticky vyrenderuje /app/ui/Home/default.latte
        $this->render();
    }

    /**
     * Získání lokalizovaného titulku
     */
    private function getLocalizedTitle(): string
    {
        return match($this->lang) {
            'en' => 'Welcome to ' . Config::get('site.name'),
            'de' => 'Willkommen bei ' . Config::get('site.name'),
            'sk' => 'Vitajte na ' . Config::get('site.name'),
            default => 'Vítejte na ' . Config::get('site.name'),
        };
    }

    /**
     * Získání uvítací zprávy podle jazyka
     */
    private function getWelcomeMessage(): string
    {
        return match($this->lang) {
            'en' => 'Discover our amazing content and services.',
            'de' => 'Entdecken Sie unsere erstaunlichen Inhalte und Dienstleistungen.',
            'sk' => 'Objavte náš úžasný obsah a služby.',
            default => 'Objevte náš úžasný obsah a služby.',
        };
    }

    /**
     * Získání vybraných článků
     * V reálu by to byl dotaz do databáze
     */
    private function getFeaturedArticles(): array
    {
        // Simulace dat - v reálu použij Nette Database
        return [
            [
                'id' => 1,
                'title' => $this->lang === 'cs' ? 'První článek' : 'First Article',
                'slug' => 'prvni-clanek',
                'excerpt' => $this->lang === 'cs'
                    ? 'Krátký popis prvního článku...'
                    : 'Short description of the first article...',
                'image' => '/assets/images/article1.jpg',
                'date' => '2025-10-01',
            ],
            [
                'id' => 2,
                'title' => $this->lang === 'cs' ? 'Druhý článek' : 'Second Article',
                'slug' => 'druhy-clanek',
                'excerpt' => $this->lang === 'cs'
                    ? 'Krátký popis druhého článku...'
                    : 'Short description of the second article...',
                'image' => '/assets/images/article2.jpg',
                'date' => '2025-10-05',
            ],
            [
                'id' => 3,
                'title' => $this->lang === 'cs' ? 'Třetí článek' : 'Third Article',
                'slug' => 'treti-clanek',
                'excerpt' => $this->lang === 'cs'
                    ? 'Krátký popis třetího článku...'
                    : 'Short description of the third article...',
                'image' => '/assets/images/article3.jpg',
                'date' => '2025-10-10',
            ],
        ];
    }

    /**
     * Získání statistik pro homepage
     */
    private function getStats(): array
    {
        return [
            'articles' => 156,
            'visitors' => 12540,
            'projects' => 42,
            'years' => 8,
        ];
    }



    /**
     * Vytvoření testovacího formuláře
     */
    protected function createComponentTestForm(): \Nette\Forms\Form
    {
        $form = \Core\Forms\FormFactory::create();

        // Jméno
        $form->addText('name', 'Vaše jméno:')
            ->setHtmlAttribute('placeholder', 'Jan Novák');

        // Email
        $form->addEmail('email', 'Váš email:')
            ->setHtmlAttribute('placeholder', 'jan@example.com');

        // Zpráva
        $form->addTextArea('message', 'Zpráva:')
            ->setHtmlAttribute('rows', 4)
            ->setHtmlAttribute('placeholder', 'Vaše zpráva...');

        // Odeslat
        $form->addSubmit('send', 'Odeslat testovací formulář');

        // Handler po odeslání
        $form->onSuccess[] = [$this, 'testFormSucceeded'];

        return $form;
    }

    /**
     * Zpracování testovacího formuláře
     */
    public function testFormSucceeded(\Nette\Forms\Form $form, \stdClass $values): void
    {
        try {
            // Pro demo: náhodně vyhodíme chybu (50% šance)
            if (rand(0, 1) === 1) {
                throw new \Exception('Simulovaná chyba při zpracování formuláře');
            }

            // Úspěch
            $this->flashMessage(
                "Formulář odeslán! Jméno: {$values->name}, Email: {$values->email}",
                'success'
            );

        } catch (\Nette\Database\DriverException $e) {
            // Databázová chyba
            \Tracy\Debugger::log($e, \Tracy\Debugger::ERROR);

            $this->flashMessage(
                'Omlouváme se, ale nepodařilo se uložit data do databáze. Zkuste to prosím později.',
                'error'
            );

        } catch (\Exception $e) {
            // Obecná chyba
            \Tracy\Debugger::log($e, \Tracy\Debugger::ERROR);

            $this->flashMessage(
                'Omlouváme se, ale při zpracování formuláře došlo k chybě. Zkuste to prosím znovu.',
                'error'
            );
        }

        // Redirect v každém případě (úspěch i chyba)
        $this->redirect('Home:default');
    }
}
