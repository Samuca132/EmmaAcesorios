import { Component, input } from '@angular/core';

@Component({
  selector: 'app-page-header',
  template: `
    <header class="page-header">
      <div>
        <h1>{{ titulo() }}</h1>
        @if (subtitulo()) {
          <p class="text-muted sub">{{ subtitulo() }}</p>
        }
      </div>
      <div class="acciones"><ng-content /></div>
    </header>
  `,
  styles: `
    .sub { margin: 4px 0 0; }
    .acciones { display: flex; gap: 8px; flex-wrap: wrap; }
  `,
})
export class PageHeader {
  readonly titulo = input.required<string>();
  readonly subtitulo = input('');
}
