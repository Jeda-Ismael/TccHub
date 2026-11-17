import { ChangeEvent, DragEvent, useState } from 'react';
import { FileDownIcon } from './icons';

const MAX_BYTES = 20 * 1024 * 1024;

export function formatFileSize(bytes: number): string {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
}

/**
 * Campo de PDF em português (o <input type="file"> do navegador mostra
 * "Choose File" conforme o idioma do sistema). Aceita clique ou arrastar,
 * e confere tipo e tamanho antes de enviar; a API confere de novo.
 */
export function FileDropzone({
  id,
  label,
  file,
  onChange,
  disabled = false,
}: {
  id: string;
  label: string;
  file: File | null;
  onChange: (file: File | null) => void;
  disabled?: boolean;
}) {
  const [dragging, setDragging] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function pick(candidate: File | undefined | null) {
    setError(null);
    if (!candidate) return;
    const isPdf = candidate.type === 'application/pdf' || candidate.name.toLowerCase().endsWith('.pdf');
    if (!isPdf) {
      setError('Escolha um arquivo PDF.');
      return;
    }
    if (candidate.size > MAX_BYTES) {
      setError('O PDF pode ter no máximo 20 MB.');
      return;
    }
    onChange(candidate);
  }

  function handleInput(e: ChangeEvent<HTMLInputElement>) {
    pick(e.target.files?.[0]);
    // Permite escolher o mesmo arquivo de novo depois de remover
    e.target.value = '';
  }

  function handleDrop(e: DragEvent<HTMLLabelElement>) {
    e.preventDefault();
    setDragging(false);
    if (!disabled) pick(e.dataTransfer.files?.[0]);
  }

  function clear() {
    setError(null);
    onChange(null);
  }

  return (
    <div className="form-group">
      <span className="field-label" id={`${id}-label`}>
        {label}
      </span>
      {file ? (
        <div className="dropzone dropzone-filled">
          <FileDownIcon size={20} />
          <span className="dropzone-name">{file.name}</span>
          <span className="muted">{formatFileSize(file.size)}</span>
          <button type="button" className="link-button" onClick={clear} disabled={disabled}>
            Trocar arquivo
          </button>
        </div>
      ) : (
        <label
          htmlFor={id}
          className={`dropzone${dragging ? ' dropzone-active' : ''}${disabled ? ' dropzone-disabled' : ''}`}
          onDragOver={(e) => {
            e.preventDefault();
            if (!disabled) setDragging(true);
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={handleDrop}
        >
          <FileDownIcon size={22} />
          <span>
            <strong>Escolha o PDF</strong> ou arraste o arquivo para cá
          </span>
          <span className="muted">Só PDF, até 20 MB</span>
        </label>
      )}
      <input
        id={id}
        className="visually-hidden"
        type="file"
        accept="application/pdf,.pdf"
        aria-labelledby={`${id}-label`}
        onChange={handleInput}
        disabled={disabled}
      />
      {error && (
        <p className="error-text" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
