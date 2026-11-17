import { useEffect, useRef, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useMarkAllNotificationsRead, useMarkNotificationRead, useNotifications, useUnreadCount } from '../api/hooks';
import { AppNotification, Role } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import { QueryStatus } from './QueryStatus';
import { formatDateTime } from '../utils/dates';

/** Tela do TCC conforme o papel com que o aviso deve ser aberto. */
export function notificationPath(n: AppNotification): string | null {
  if (!n.tccId || !n.as) return null;
  const paths: Record<Role, string> = {
    STUDENT: '/student',
    ADVISOR: `/advisor/${n.tccId}`,
    EVALUATOR: `/evaluator/${n.tccId}`,
    COORDINATION: `/coordination/tcc/${n.tccId}`,
  };
  return paths[n.as];
}

/** Sininho do topo: contador (atualizado a cada 30 s) e lista dos avisos mais recentes. */
export function NotificationBell() {
  const [open, setOpen] = useState(false);
  const { data: unread = 0 } = useUnreadCount();
  const { data, isLoading, error } = useNotifications(open);
  const markRead = useMarkNotificationRead();
  const markAll = useMarkAllNotificationsRead();
  const { user, setActiveRole } = useAuth();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const ref = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);

  // Chegou aviso novo: algo mudou num TCC, então as telas abertas recarregam.
  // A primeira leitura do contador só registra o valor.
  const previous = useRef<number | null>(null);
  useEffect(() => {
    if (previous.current !== null && unread > previous.current) {
      qc.invalidateQueries({ queryKey: ['tccs'] });
      qc.invalidateQueries({ queryKey: ['tcc'] });
      qc.invalidateQueries({ queryKey: ['notifications', 'list'] });
    }
    previous.current = unread;
  }, [unread, qc]);

  // Fecha ao clicar fora ou apertar Esc
  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (!ref.current?.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  function openNotification(n: AppNotification) {
    if (!n.read) markRead.mutate(n.id);
    const path = notificationPath(n);
    if (!path) return;
    if (n.as && user?.roles.includes(n.as)) setActiveRole(n.as);
    setOpen(false);
    navigate(path);
  }

  const label = unread > 0 ? `Notificações (${unread} não lidas)` : 'Notificações';

  return (
    <div className="bell" ref={ref}>
      <button ref={buttonRef} type="button" className="bell-button" aria-label={label} aria-expanded={open} onClick={() => setOpen((v) => !v)}>
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path
            fill="currentColor"
            d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3.5a1.5 1.5 0 0 0-3 0v.66A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"
          />
        </svg>
        {unread > 0 && (
          <span className="bell-count" aria-hidden="true">
            {unread > 99 ? '99+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="bell-panel" role="dialog" aria-label="Notificações">
          <div className="bell-header">
            <strong>Notificações</strong>
            {unread > 0 && (
              <button type="button" className="link-button" onClick={() => markAll.mutate(undefined)} disabled={markAll.isPending}>
                Marcar todas como lidas
              </button>
            )}
          </div>
          {(isLoading || error) && (
            <div className="bell-empty">
              <QueryStatus isLoading={isLoading} error={error} />
            </div>
          )}
          {data && data.items.length === 0 && <p className="muted bell-empty">Nenhuma notificação.</p>}
          {data && data.items.length > 0 && (
            <ul className="bell-list">
              {data.items.map((n) => (
                <li key={n.id}>
                  <button type="button" className={`bell-item${n.read ? '' : ' unread'}`} onClick={() => openNotification(n)}>
                    <span className="bell-item-title">{n.title}</span>
                    <span className="bell-item-body">{n.body}</span>
                    <span className="bell-item-date">{formatDateTime(n.createdAt)}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
