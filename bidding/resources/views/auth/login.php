<?php
$errors = session('errors') ?: new \Illuminate\Support\MessageBag;

$content = ob_start();
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="text-center mb-4">
                <i class="fas fa-gavel fa-3x text-primary"></i>
                <h2 class="mt-2">Bidding System</h2>
                <p class="text-muted">Sistema de Capitalização de Licitações</p>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Login</h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?= route('login') ?>">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control <?= $errors->has('email') ? 'is-invalid' : '' ?>"
                                       id="email" name="email" value="<?= old('email') ?>" required autofocus>
                                <?php if ($errors->has('email')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('email') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label for="password" class="form-label">Senha</label>
                                <a href="#" class="text-decoration-none small">Esqueceu a senha?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control <?= $errors->has('password') ? 'is-invalid' : '' ?>"
                                       id="password" name="password" required>
                                <?php if ($errors->has('password')): ?>
                                    <div class="invalid-feedback">
                                        <?= $errors->first('password') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Lembrar-me</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i> Entrar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$styles = <<<HTML
<style>
    body {
        background-color: #f8f9fa;
    }
    .container {
        margin-top: 100px;
    }
</style>
HTML;

// Renderizar o layout base com o conteúdo de login
include(resource_path('views/layout/app.php'));
?>
