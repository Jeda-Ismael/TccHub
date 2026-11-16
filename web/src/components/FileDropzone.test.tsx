import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { FileDropzone, formatFileSize } from './FileDropzone';

describe('área de upload', () => {
  it('formata o tamanho em KB ou MB', () => {
    expect(formatFileSize(500)).toBe('1 KB');
    expect(formatFileSize(300 * 1024)).toBe('300 KB');
    expect(formatFileSize(2.5 * 1024 * 1024)).toBe('2,5 MB');
  });

  it('aceita PDF arrastado e recusa arquivo grande demais', () => {
    const onChange = vi.fn();
    render(<FileDropzone id="f" label="Arquivo PDF" file={null} onChange={onChange} />);
    const zone = screen.getByText('Escolha o PDF').closest('label')!;

    fireEvent.dragOver(zone);
    expect(zone).toHaveClass('dropzone-active');
    const pdf = new File(['%PDF'], 'a.pdf', { type: 'application/pdf' });
    fireEvent.drop(zone, { dataTransfer: { files: [pdf] } });
    expect(onChange).toHaveBeenCalledWith(pdf);

    const big = new File(['x'], 'b.pdf', { type: 'application/pdf' });
    Object.defineProperty(big, 'size', { value: 21 * 1024 * 1024 });
    fireEvent.drop(zone, { dataTransfer: { files: [big] } });
    expect(screen.getByRole('alert')).toHaveTextContent('no máximo 20 MB');
    expect(onChange).toHaveBeenCalledTimes(1);
  });

  it('não aceita arquivo quando está desativada', () => {
    const onChange = vi.fn();
    render(<FileDropzone id="f" label="Arquivo PDF" file={null} onChange={onChange} disabled />);
    const zone = screen.getByText('Escolha o PDF').closest('label')!;

    fireEvent.drop(zone, { dataTransfer: { files: [new File(['%PDF'], 'a.pdf', { type: 'application/pdf' })] } });

    expect(onChange).not.toHaveBeenCalled();
  });
});
