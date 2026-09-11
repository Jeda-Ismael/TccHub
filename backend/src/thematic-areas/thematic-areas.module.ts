import { Module } from '@nestjs/common';
import { ThematicAreasService } from './thematic-areas.service';
import { ThematicAreasController } from './thematic-areas.controller';

@Module({
  providers: [ThematicAreasService],
  controllers: [ThematicAreasController],
  exports: [ThematicAreasService],
})
export class ThematicAreasModule {}
