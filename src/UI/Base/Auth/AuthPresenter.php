<?php declare(strict_types=1);

namespace UI\Base\Auth;

use UI\Base\BasePresenter;
use Core\Container;
use Core\FormFactory;
use Nette\Forms\Form;
use Models\Customer\CustomerAuthService;
use Models\Customer\PasswordResetService;

class AuthPresenter extends BasePresenter
{
    public function __construct(
        Container $container,
        protected CustomerAuthService $customerAuthService,
        protected PasswordResetService $passwordResetService,
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

    /**
     * Forgot password page
     */
    public function actionForgotPassword(): void
    {
        // If already logged in, redirect to homepage
        if ($this->isLoggedIn()) {
            $this->redirect('Homepage:default');
        }
    }

    /**
     * Render forgot password page
     */
    public function renderForgotPassword(): void
    {
        $this->assign('pageTitle', 'Zapomenuté heslo');
        $this->render();
    }

    /**
     * Create forgot password form component
     */
    protected function createComponentForgotPasswordForm(): Form
    {
        $form = FormFactory::create();

        $form->addEmail('email', 'E-mail:')
            ->setRequired('Vyplňte e-mail')
            ->setAttribute('placeholder', 'vas@email.cz');

        $form->addSubmit('submit', 'Odeslat odkaz pro reset hesla');

        $form->onSuccess[] = [$this, 'forgotPasswordFormSucceeded'];

        return $form;
    }

    /**
     * Process forgot password form
     */
    public function forgotPasswordFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            // Request password reset
            $this->passwordResetService->requestReset($values->email);

            // Success - redirect to login with message
            $this->flashMessage('Odkaz pro reset hesla byl odeslán na váš e-mail', 'success');
            $this->redirect('Auth:login');

        } catch (\Exception $e) {
            // Error - show in form
            $form->addError($e->getMessage());
        }
    }

    /**
     * Reset password page (from email link)
     *
     * @param string|null $token Token from URL parameter
     */
    public function actionResetPassword(?string $token = null): void
    {
        // If already logged in, redirect to homepage
        if ($this->isLoggedIn()) {
            $this->redirect('Homepage:default');
        }

        // Token může být v GET (initial load) nebo POST (form submit)
        $token = $token ?? $this->getParam('token');

        if (!$token) {
            $this->flashMessage('Neplatný odkaz pro reset hesla', 'danger');
            $this->redirect('Auth:login');
        }

        // Validuj token jen při GET (initial load, ne při form submit)
        if (!$this->isPost()) {
            $tokenData = $this->passwordResetService->validateToken($token);

            if (!$tokenData) {
                $this->flashMessage('Odkaz pro reset hesla je neplatný nebo vypršel', 'danger');
                $this->redirect('Auth:forgotPassword');
            }
        }
    }

    /**
     * Render reset password page
     */
    public function renderResetPassword(): void
    {
        $this->assign('pageTitle', 'Nastavení nového hesla');
        $this->render();
    }

    /**
     * Create reset password form component
     */
    protected function createComponentResetPasswordForm(): Form
    {
        $form = FormFactory::create();

        // Get token from URL parameter
        $token = $this->getParam('token');

        // Hidden field for token
        $form->addHidden('token')
            ->setDefaultValue($token);

        $form->addPassword('password', 'Nové heslo:')
            ->setRequired('Vyplňte nové heslo')
            ->addRule(Form::MinLength, 'Heslo musí mít alespoň %d znaků', 6);

        $form->addPassword('passwordVerify', 'Nové heslo znovu:')
            ->setRequired('Vyplňte heslo znovu')
            ->addRule(Form::Equal, 'Hesla se neshodují', $form['password']);

        $form->addSubmit('submit', 'Změnit heslo');

        $form->onSuccess[] = [$this, 'resetPasswordFormSucceeded'];

        return $form;
    }

    /**
     * Process reset password form
     */
    public function resetPasswordFormSucceeded(Form $form, \stdClass $values): void
    {
        \Tracy\Debugger::barDump('SUCCESS HANDLER CALLED!', 'Submit Debug');
        \Tracy\Debugger::barDump($values, 'Form values');
        try {
            // Reset password using token
            $customerId = $this->passwordResetService->resetPassword(
                $values->token,
                $values->password
            );

            // Success - redirect to login
            $this->flashMessage('Heslo bylo úspěšně změněno. Nyní se můžete přihlásit.', 'success');
            $this->redirect('Auth:login');

        } catch (\Exception $e) {
            // Error - show in form
            $form->addError($e->getMessage());
        }
    }
}