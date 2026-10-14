<div class="page-heading"><div><p class="eyebrow">ADMINISTRACIÓN</p><h1>Usuarios</h1><p class="muted">Cuentas y permisos de acceso.</p></div></div>
<section class="installation-panel"><div class="table-scroll" tabindex="0" role="region" aria-label="Cuentas de usuario"><table><thead><tr><th scope="col">Usuario</th><th scope="col">Nombre</th><th scope="col">Permiso</th><th scope="col">Estado</th></tr></thead><tbody>
<?php $roles = ['admin' => 'Administrador', 'operator' => 'Operador', 'viewer' => 'Lectura']; foreach ($users as $account): ?>
<tr><th scope="row"><?= e($account['username']) ?></th><td><?= e($account['display_name']) ?></td><td><?= e($roles[$account['role']] ?? $account['role']) ?></td><td><span class="badge"><?= $account['active'] ? 'Activa' : 'Desactivada' ?></span></td></tr>
<?php endforeach ?>
</tbody></table></div></section>
