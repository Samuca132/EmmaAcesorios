import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  return auth.token ? true : inject(Router).createUrlTree(['/login']);
};

/** Solo administradores (rol 1). El backend también lo valida. */
export const adminGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  if (!auth.token) {
    return inject(Router).createUrlTree(['/login']);
  }
  return auth.esAdmin() ? true : inject(Router).createUrlTree(['/inicio']);
};

export const invitadoGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  return auth.token ? inject(Router).createUrlTree(['/inicio']) : true;
};
