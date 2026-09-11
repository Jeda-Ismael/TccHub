import { Body, Controller, Delete, Get, Param, Post, Put, UseGuards } from '@nestjs/common';
import { Role } from '@prisma/client';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { RolesGuard } from '../common/guards/roles.guard';
import { Roles } from '../common/decorators/roles.decorator';
import { ThematicAreasService } from './thematic-areas.service';
import { ThematicAreaDto } from './dto/thematic-area.dto';

@Controller('thematic-areas')
@UseGuards(JwtAuthGuard, RolesGuard)
export class ThematicAreasController {
  constructor(private service: ThematicAreasService) {}

  @Get()
  findAll() {
    return this.service.findAll();
  }

  @Post()
  @Roles(Role.COORDINATION)
  create(@Body() dto: ThematicAreaDto) {
    return this.service.create(dto);
  }

  @Put(':id')
  @Roles(Role.COORDINATION)
  update(@Param('id') id: string, @Body() dto: ThematicAreaDto) {
    return this.service.update(id, dto);
  }

  @Delete(':id')
  @Roles(Role.COORDINATION)
  remove(@Param('id') id: string) {
    return this.service.remove(id);
  }
}
