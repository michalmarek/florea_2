<?php declare(strict_types=1);

namespace UI\Base\Auth;

use UI\Base\BasePresenter;
use Core\Container;
use Core\FormFactory;
use Nette\Forms\Form;
use Models\Customer\CustomerAuthService;

class AuthPresenter extends BasePresenter
{
    public function __construct(
        Container $container,
        protected CustomerAuthService $customerAuthService,
    )
    {
        parent::__construct($container);
    }

    /**
     * Login form page
     */
    public function actionLogin(): void
    {
        // If already logged in, redirect to homepage
        if ($this->isLoggedIn()) {
            $this->redirect('Homepage:default');
        }
    }

    /**
     * Render login page
     */
    public function renderLogin(): void
    {
        $this->assign('pageTitle', 'Přihlášení');
        $this->render();
    }

    /**
     * Create login form component
     */
    protected function createComponentLoginForm(): Form
    {
        $form = FormFactory::create();

        $form->addText('login', 'Přihlašovací jméno:')
            ->setRequired('Vyplňte přihlašovací jméno');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Vyplňte heslo');

        $form->addCheckbox('rememberMe', 'Zapamatovat si mě');

        $form->addSubmit('submit', 'Přihlásit se');

        $form->onSuccess[] = [$this, 'loginFormSucceeded'];

        return $form;
    }

    /**
     * Process login form
     */
    public function loginFormSucceeded(Form $form, \stdClass $values): void
    {
        $customer = $this->customerAuthService->login(
            $values->login,
            $values->password,
            $values->rememberMe
        );

        if ($customer) {
            $this->flashMessage('Byli jste úspěšně přihlášeni', 'success');
            $this->redirect('Homepage:default');
        } else {
            $form->addError('Nesprávné přihlašovací jméno nebo heslo');
        }
    }

    /**
     * Create registration form component
     */
    protected function createComponentRegisterForm(): Form
    {
        $form = FormFactory::create();

        $form->addText('login', 'Přihlašovací jméno:')
            ->setRequired('Vyplňte přihlašovací jméno')
            ->addRule(Form::MinLength, 'Login musí mít alespoň %d znaky', 3);

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Vyplňte e-mail');

        $form->addText('firstName', 'Jméno:')
            ->setRequired('Vyplňte jméno');

        $form->addText('lastName', 'Příjmení:')
            ->setRequired('Vyplňte příjmení');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Vyplňte heslo')
            ->addRule(Form::MinLength, 'Heslo musí mít alespoň %d znaků', 6);

        $form->addPassword('passwordVerify', 'Heslo znovu:')
            ->setRequired('Vyplňte heslo znovu')
            ->addRule(Form::Equal, 'Hesla se neshodují', $form['password']);

        $form->addSubmit('submit', 'Registrovat se');

        $form->onSuccess[] = [$this, 'registerFormSucceeded'];

        return $form;
    }

    /**
     * Process registration form
     */
    public function registerFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            // Register new customer
            $customer = $this->customerAuthService->register([
                'login' => $values->login,
                'email' => $values->email,
                'firstName' => $values->firstName,
                'lastName' => $values->lastName,
                'password' => $values->password,
            ]);

            $this->flashMessage('Registrace byla úspěšná. Byli jste automaticky přihlášeni.', 'success');
            $this->redirect('Homepage:default');

        } catch (\Exception $e) {
            $form->addError($e->getMessage());
        }
    }

    /**
     * Logout action
     */
    public function actionLogout(): void
    {
        $this->customerAuthService->logout();
        $this->flashMessage('Byli jste odhlášeni', 'info');
        $this->redirect('Homepage:default');
    }

    /**
     * Registration form page
     */
    public function actionRegister(): void
    {
        // If already logged in, redirect to homepage
        if ($this->isLoggedIn()) {
            $this->redirect('Homepage:default');
        }
    }

    /**
     * Render registration page
     */
    public function renderRegister(): void
    {
        $this->assign('pageTitle', 'Registrace');
        $this->render();
    }
}