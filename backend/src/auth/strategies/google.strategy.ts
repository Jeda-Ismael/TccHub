import { Injectable } from '@nestjs/common';
import { PassportStrategy } from '@nestjs/passport';
import { Strategy, VerifyCallback } from 'passport-google-oauth20';
import { ConfigService } from '@nestjs/config';

@Injectable()
export class GoogleStrategy extends PassportStrategy(Strategy, 'google') {
  constructor(config: ConfigService) {
    super({
      // Fallback evita que o backend quebre ao subir sem credenciais do Google;
      // o login por e-mail/senha continua funcionando normalmente.
      clientID: config.get<string>('GOOGLE_CLIENT_ID') || 'google-oauth-nao-configurado',
      clientSecret: config.get<string>('GOOGLE_CLIENT_SECRET') || 'google-oauth-nao-configurado',
      callbackURL: config.get<string>('GOOGLE_CALLBACK_URL'),
      scope: ['email', 'profile'],
    });
  }

  async validate(
    _accessToken: string,
    _refreshToken: string,
    profile: any,
    done: VerifyCallback,
  ) {
    const email = profile.emails?.[0]?.value;
    const name = profile.displayName;
    done(null, { googleId: profile.id, email, name });
  }
}
