<div class="page-heading"><div><p class="eyebrow">ADMINISTRACIÓN</p><h1>Usuarios y roles</h1><p class="muted">Cuentas de comandantes y permisos de acceso.</p></div><button type="button" class="button-primary" data-action="user-create">+ Crear usuario</button></div>
<p class="data-note">Administrador: cuentas y toda la gestión. Operador: planificación y entregas de terceros. Comandante: entregas propias, listas y abastecimiento. Todos los usuarios autenticados gestionan su lista y la de la flota.</p>
<section class="installation-panel"><div class="table-scroll" tabindex="0" role="region" aria-label="Cuentas de usuario"><table><thead><tr><th scope="col">Usuario</th><th scope="col">Nombre</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php $roles=['admin'=>'Administrador','operator'=>'Operador','viewer'=>'Comandante']; foreach($users as $account): ?>
<tr><th scope="row"><?= e($account['username']) ?></th><td><?= e($account['display_name']) ?></td><td><?= e($roles[$account['role']]) ?></td><td><span class="badge"><?= $account['deleted_at']?'Eliminada':($account['active']?'Activa':'Desactivada') ?></span></td><td><div class="row-actions">
<?php if($account['deleted_at']): ?><button type="button" class="button-quiet" data-action="user-restore" data-record="<?= record_attribute($account) ?>">Restaurar usuario</button>
<?php else: ?><button type="button" class="button-quiet" data-action="user-edit" data-record="<?= record_attribute($account) ?>">Editar usuario</button><button type="button" class="button-quiet" data-action="user-password" data-record="<?= record_attribute($account) ?>">Restablecer contraseña</button><button type="button" class="button-quiet" data-action="user-delete" data-record="<?= record_attribute($account) ?>" <?= $account['id']===$user['id']?'disabled':'' ?>>Eliminar usuario</button><?php endif ?>
</div></td></tr>
<?php endforeach ?></tbody></table></div></section>
<p class="data-note">Eliminar revoca el acceso y conserva aportes e historial. No se permite eliminar/desactivar al último administrador activo.</p>
<?php $paginationQuery=['page'=>'users']; require __DIR__.'/pagination.php'; ?>
