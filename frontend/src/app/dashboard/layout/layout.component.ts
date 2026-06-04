import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../auth/services/auth.service';

@Component({
  selector: 'app-layout',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss',
})
export class LayoutComponent {
  isSidebarOpen = true;
  userName = '';

  constructor(private authService: AuthService) {
    const user = this.authService.getCurrentUser();
    this.userName = user?.name || 'Admin';
    this.authService.getAuthenticatedUser().subscribe({
      next: ({ user: refreshedUser }) => {
        this.userName = refreshedUser.name;
      },
    });
  }

  toggleSidebar(): void {
    this.isSidebarOpen = !this.isSidebarOpen;
  }

  hasPermission(permission: string): boolean {
    return this.authService.hasPermission(permission);
  }

  logout(): void {
    if (confirm('¿Estás seguro de cerrar sesión?')) {
      this.authService.logout().subscribe();
    }
  }
}
