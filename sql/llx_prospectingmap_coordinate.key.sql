-- ============================================================================
-- Copyright (C) 2017	 Open-DSI 	 <support@open-dsi.fr>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ===========================================================================

ALTER TABLE llx_prospectingmap_coordinate ADD UNIQUE INDEX uk_prospectingmap_c_fk_soc (fk_soc);

ALTER TABLE llx_prospectingmap_coordinate ADD CONSTRAINT fk_prospectingmap_c_fk_soc         FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid);
