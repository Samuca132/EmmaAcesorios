import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CanjesComponent } from './canjes.component';

describe('CanjesComponent', () => {
  let component: CanjesComponent;
  let fixture: ComponentFixture<CanjesComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CanjesComponent]
    })
    .compileComponents();
    
    fixture = TestBed.createComponent(CanjesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
