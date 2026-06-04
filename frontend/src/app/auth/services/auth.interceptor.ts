import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from './auth.service';
import { catchError, throwError } from 'rxjs';
import { Router } from '@angular/router';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  const token = authService.getToken();
  const isFormData = req.body instanceof FormData;

  // Clone request with credentials and headers
  let clonedReq = req.clone({
    withCredentials: true, // Important for CORS with cookies
  });

  // Add authorization header if token exists
  if (token) {
    clonedReq = clonedReq.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
        'Accept': 'application/json',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      },
    });
  } else {
    clonedReq = clonedReq.clone({
      setHeaders: {
        'Accept': 'application/json',
        ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      },
    });
  }

  // Handle response errors
  return next(clonedReq).pipe(
    catchError((error) => {
      // If 401 Unauthorized, logout and redirect to login
      if (error.status === 401) {
        localStorage.removeItem('access_token');
        localStorage.removeItem('user');
        router.navigate(['/login']);
      }

      return throwError(() => error);
    })
  );
};
