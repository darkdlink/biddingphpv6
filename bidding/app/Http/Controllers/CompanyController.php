<?php
// app/Http/Controllers/CompanyController.php
namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::orderBy('name')->paginate(10);

        $content = $this->renderIndex($companies);
        return response($content);
    }

    public function create()
    {
        $content = $this->renderCreate();
        return response($content);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'cnpj' => 'required|max:18|unique:companies',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->route('companies.create')
                ->withErrors($validator)
                ->withInput();
        }

        Company::create($request->all());

        return redirect()->route('companies.index')
            ->with('success', 'Empresa cadastrada com sucesso!');
    }

    public function show($id)
    {
        $company = Company::with('biddings', 'documents')->findOrFail($id);

        $content = $this->renderShow($company);
        return response($content);
    }

    public function edit($id)
    {
        $company = Company::findOrFail($id);

        $content = $this->renderEdit($company);
        return response($content);
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'cnpj' => 'required|max:18|unique:companies,cnpj,' . $id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->route('companies.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $company->update($request->all());

        return redirect()->route('companies.show', $id)
            ->with('success', 'Empresa atualizada com sucesso!');
    }

    public function destroy($id)
    {
        $company = Company::findOrFail($id);
        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Empresa removida com sucesso!');
    }

    private function renderIndex($companies)
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Empresas - Sistema de Licitações</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body>
            <?php include(resource_path('views/layout/header.php')); ?>

            <div class="container-fluid py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Empresas</h1>
                    <a href="<?= route('companies.create') ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Nova Empresa
                    </a>
                </div>

                <?php if (session('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= session('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>CNPJ</th>
                                        <th>Email</th>
                                        <th>Telefone</th>
                                        <th>Licitações</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($companies) > 0): ?>
                                        <?php foreach ($companies as $company): ?>
                                            <tr>
                                                <td><?= $company->name ?></td>
                                                <td><?= $company->cnpj ?></td>
                                                <td><?= $company->email ?: '-' ?></td>
                                                <td><?= $company->phone ?: '-' ?></td>
                                                <td><?= $company->biddings->count() ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="<?= route('companies.show', $company->id) ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="<?= route('companies.edit', $company->id) ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger"
                                                                onclick="confirmDelete(<?= $company->id ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Nenhuma empresa encontrada</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-center mt-4">
                            <?= $companies->links() ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de confirmação de exclusão -->
            <div class="modal fade" id="deleteModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirmar Exclusão</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Tem certeza que deseja excluir esta empresa?</p>
                            <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <form id="deleteForm" method="POST" action="">
                                <?= csrf_field() ?>
                                <?= method_field('DELETE') ?>
                                <button type="submit" class="btn btn-danger">Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php include(resource_path('views/layout/footer.php')); ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <script>
                function confirmDelete(id) {
                    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                    document.getElementById('deleteForm').action = `/companies/${id}`;
                    deleteModal.show();
                }
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function renderCreate()
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="pt-br">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Nova Empresa - Sistema de Licitações</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body>
            <?php include(resource_path('views/layout/header.php')); ?>

            <div class="container-fluid py-4">
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
                        <h5 class="mb-0">Nova Empresa</h5>
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
            </div>

            <?php include(resource_path('views/layout/footer.php')); ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
