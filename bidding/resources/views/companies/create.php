<?php
$title = 'Nova Empresa - Sistema de Licitações';
$errors = session('errors') ?: new \Illuminate\Support\MessageBag;

$content = ob_start();
?>
<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= route('dashboard') ?>">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= route('companies.index') ?>">Empresas</a></li>
                <li class="breadcrumb-item active">Nova Empresa</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nova Empresa</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= route('companies.store') ?>">
            <?= csrf_field() ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nome da Empresa *</label>
                    <input type="text" class="form-control <?= $errors->has('name') ? 'is-invalid' : '' ?>"
                           id="name" name="name" value="<?= old('name') ?>" required>
                    <?php if ($errors->has('name')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('name') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="cnpj" class="form-label">CNPJ *</label>
                    <input type="text" class="form-control <?= $errors->has('cnpj') ? 'is-invalid' : '' ?>"
                           id="cnpj" name="cnpj" value="<?= old('cnpj') ?>" required>
                    <?php if ($errors->has('cnpj')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('cnpj') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control <?= $errors->has('email') ? 'is-invalid' : '' ?>"
                           id="email" name="email" value="<?= old('email') ?>">
                    <?php if ($errors->has('email')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('email') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Telefone</label>
                    <input type="text" class="form-control <?= $errors->has('phone') ? 'is-invalid' : '' ?>"
                           id="phone" name="phone" value="<?= old('phone') ?>">
                    <?php if ($errors->has('phone')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('phone') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="address" class="form-label">Endereço</label>
                    <input type="text" class="form-control <?= $errors->has('address') ? 'is-invalid' : '' ?>"
                           id="address" name="address" value="<?= old('address') ?>">
                    <?php if ($errors->has('address')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('address') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="city" class="form-label">Cidade</label>
                    <input type="text" class="form-control <?= $errors->has('city') ? 'is-invalid' : '' ?>"
                           id="city" name="city" value="<?= old('city') ?>">
                    <?php if ($errors->has('city')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('city') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="state" class="form-label">Estado</label>
                    <input type="text" class="form-control <?= $errors->has('state') ? 'is-invalid' : '' ?>"
                           id="state" name="state" value="<?= old('state') ?>">
                    <?php if ($errors->has('state')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('state') ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label for="zip_code" class="form-label">CEP</label>
                    <input type="text" class="form-control <?= $errors->has('zip_code') ? 'is-invalid' : '' ?>"
                           id="zip_code" name="zip_code" value="<?= old('zip_code') ?>">
                    <?php if ($errors->has('zip_code')): ?>
                        <div class="invalid-feedback">
                            <?= $errors->first('zip_code') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Descrição</label>
                <textarea class="form-control <?= $errors->has('description') ? 'is-invalid' : '' ?>"
                          id="description" name="description" rows="4"><?= old('description') ?></textarea>
                <?php if ($errors->has('description')): ?>
                    <div class="invalid-feedback">
                        <?= $errors->first('description') ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-end">
                <a href="<?= route('companies.index') ?>" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();

$scripts = <<<HTML
<script>
    // Máscara para CNPJ
    document.getElementById('cnpj').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');

        if (value.length > 14) {
            value = value.substring(0, 14);
        }

        if (value.length > 12) {
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
        } else if (value.length > 8) {
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d*).*/, '$1.$2.$3/$4');
        } else if (value.length > 5) {
            value = value.replace(/^(\d{2})(\d{3})(\d*).*/, '$1.$2.$3');
        } else if (value.length > 2) {
            value = value.replace(/^(\d{2})(\d*).*/, '$1.$2');
        }

        e.target.value = value;
    });

    // Máscara para CEP
    document.getElementById('zip_code').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');

        if (value.length > 8) {
            value = value.substring(0, 8);
        }

        if (value.length > 5) {
            value = value.replace(/^(\d{5})(\d{3}).*/, '$1-$2');
        }

        e.target.value = value;
    });

    // Máscara para Telefone
    document.getElementById('phone').addEventListener('input', function (e) {
        let value = e.target.value.replace(/\D/g, '');

        if (value.length > 11) {
            value = value.substring(0, 11);
        }

        if (value.length > 10) {
            value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        } else if (value.length > 6) {
            value = value.replace(/^(\d{2})(\d{4})(\d*).*/, '($1) $2-$3');
        } else if (value.length > 2) {
            value = value.replace(/^(\d{2})(\d*).*/, '($1) $2');
        }

        e.target.value = value;
    });
</script>
HTML;

// Renderizar o layout base
include(resource_path('views/layout/app.php'));
?>
