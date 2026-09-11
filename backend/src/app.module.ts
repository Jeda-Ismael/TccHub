import { Module } from '@nestjs/common';
import { ConfigModule } from '@nestjs/config';
import { ServeStaticModule } from '@nestjs/serve-static';
import { join } from 'path';
import { PrismaModule } from './prisma/prisma.module';
import { AuthModule } from './auth/auth.module';
import { UsersModule } from './users/users.module';
import { TccModule } from './tcc/tcc.module';
import { ThematicAreasModule } from './thematic-areas/thematic-areas.module';
import { KeywordsModule } from './keywords/keywords.module';
import { PeriodsModule } from './periods/periods.module';
import { StorageModule } from './storage/storage.module';

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true }),
    ServeStaticModule.forRoot({
      rootPath: join(process.cwd(), process.env.UPLOADS_DIR || 'uploads'),
      serveRoot: '/uploads',
    }),
    PrismaModule,
    AuthModule,
    UsersModule,
    TccModule,
    ThematicAreasModule,
    KeywordsModule,
    PeriodsModule,
    StorageModule,
  ],
})
export class AppModule {}
