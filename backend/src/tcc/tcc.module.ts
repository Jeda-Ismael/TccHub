import { Module } from '@nestjs/common';
import { TccService } from './tcc.service';
import { TccController } from './tcc.controller';
import { PeriodsModule } from '../periods/periods.module';
import { StorageModule } from '../storage/storage.module';

@Module({
  imports: [PeriodsModule, StorageModule],
  providers: [TccService],
  controllers: [TccController],
})
export class TccModule {}
